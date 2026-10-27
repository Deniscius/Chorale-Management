<?php

namespace App\Services;

use App\Enums\Commission;
use App\Models\AuditEvent;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UserCommissionAssignments
{
    public function fingerprint(User $user): string
    {
        $rows = ChoirCommission::withExists([
            'members as assigned_member' => fn ($q) => $q->where('members.id', $user->member_id ?? 0),
            'instrumentAssociates as assigned_instrument_associate' => fn ($q) => $q->where('users.id', $user->id),
        ])->orderBy('id')->get()->map(fn ($entry) => [
            $entry->id, $entry->leader_id, $entry->associate_id, $entry->module_key, $entry->archive_commission,
            $entry->starts_on?->toDateString(), $entry->ends_on?->toDateString(), $entry->archived_at?->toISOString(),
            (bool) $entry->assigned_member, (bool) $entry->assigned_instrument_associate,
        ])->all();

        return hash('sha256', json_encode([$user->member_id, $rows], JSON_THROW_ON_ERROR));
    }

    public function save(int $userId, array $members, array $leaders, array $instrumentAssociates, array $archiveAssociates, string $expectedFingerprint): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $groups = compact('members', 'leaders', 'instrumentAssociates', 'archiveAssociates');
        $rules = [];
        foreach (array_keys($groups) as $key) {
            $rules[$key] = 'array|max:200';
            $rules[$key.'.*'] = 'required|integer|distinct';
        }
        Validator::make($groups, $rules)->validate();
        $groups = array_map(fn ($ids) => array_map('intval', $ids), $groups);
        DB::transaction(function () use ($userId, $groups, $expectedFingerprint) {
            app(TenantContext::class)->lockForAccountChanges();
            $user = User::lockForUpdate()->findOrFail($userId);
            $member = $user->member()->lockForUpdate()->first();
            if (! $member) {
                throw ValidationException::withMessages(['assignments' => 'La fiche choriste doit être complétée depuis Mon profil, ou restaurée si elle a été supprimée.']);
            }
            $commissions = ChoirCommission::with(['members' => fn ($q) => $q->select('members.id'), 'instrumentAssociates' => fn ($q) => $q->select('users.id')])->lockForUpdate()->get();
            if (! hash_equals($expectedFingerprint, $this->fingerprint($user))) {
                throw ValidationException::withMessages(['assignments' => 'Les affectations ont changé pendant votre saisie. Rechargez la page avant de réessayer.']);
            }
            foreach ($groups as $ids) {
                if (array_diff($ids, $commissions->modelKeys())) {
                    throw ValidationException::withMessages(['assignments' => 'Choisissez uniquement des commissions de cette chorale.']);
                }
            }
            $before = [];
            $after = [];
            foreach ($commissions as $entry) {
                $leader = in_array($entry->id, $groups['leaders'], true);
                $instrument = in_array($entry->id, $groups['instrumentAssociates'], true);
                $archive = in_array($entry->id, $groups['archiveAssociates'], true);
                $isMember = in_array($entry->id, $groups['members'], true) || $leader || $instrument || $archive;
                $private = $entry->archive_commission && Commission::from($entry->archive_commission)->hasPrivateArchive();
                if (($leader && $entry->leader_id && (int) $entry->leader_id !== $userId)
                    || ($archive && $entry->associate_id && (int) $entry->associate_id !== $userId)) {
                    throw ValidationException::withMessages(['assignments' => 'Une responsabilité est déjà attribuée dans « '.$entry->name.' ». Utilisez la gestion de cette commission pour remplacer la personne.']);
                }
                if (($instrument && $entry->module_key !== 'instruments') || ($archive && ! $private) || ($leader && ($instrument || $archive))) {
                    throw ValidationException::withMessages(['assignments' => 'Affectations incompatibles dans « '.$entry->name.' ». Un responsable ne doit pas être coché comme son propre associé.']);
                }
                $associateIds = $entry->instrumentAssociates->pluck('id')->map(fn ($id) => (int) $id)->all();
                $wasMember = $entry->members->contains('id', $member->id);
                $previous = [$wasMember, (int) $entry->leader_id === $userId, in_array($userId, $associateIds, true), (int) $entry->associate_id === $userId];
                $desired = [$isMember, $leader, $instrument, $archive];
                if ($previous === $desired) {
                    continue;
                }
                $auditKeys = ['member', 'leader', 'instrument_associate', 'archive_associate'];
                $before[$entry->id] = array_combine($auditKeys, $previous);
                $after[$entry->id] = array_combine($auditKeys, $desired);
                $leaderId = $leader ? $userId : ((int) $entry->leader_id === $userId ? null : $entry->leader_id);
                $archiveId = $archive ? $userId : ((int) $entry->associate_id === $userId ? null : $entry->associate_id);
                $associateIds = array_values(array_filter($associateIds, fn ($id) => $id !== $userId));
                if ($instrument) {
                    $associateIds[] = $userId;
                }
                if (array_slice($previous, 1) !== array_slice($desired, 1)) {
                    app(CommissionResponsibilities::class)->assign($entry->id, $leaderId, $archiveId, $entry->module_key, $associateIds);
                }
                if ($isMember) {
                    $entry->members()->syncWithoutDetaching([$member->id]);
                } else {
                    $entry->members()->detach($member->id);
                }
            }
            if ($after) {
                AuditEvent::create(['actor_id' => auth()->id(), 'subject_type' => User::class, 'subject_id' => $userId, 'action' => 'updated', 'changes' => ['before' => ['commission_assignments' => $before], 'after' => ['commission_assignments' => $after]], 'created_at' => now()]);
            }
        }, 3);
        app(CommissionAccess::class)->forget();
    }
}
