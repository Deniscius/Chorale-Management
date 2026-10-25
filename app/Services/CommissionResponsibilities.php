<?php

namespace App\Services;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CommissionResponsibilities
{
    public function assign(int $id, ?int $leaderId, ?int $associateId, ?string $module, ?array $instrumentAssociateIds = null): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        Validator::make(['leader_id' => $leaderId, 'associate_id' => $associateId, 'module_key' => $module], [
            'leader_id' => ['nullable', Rule::exists('users', 'id')],
            'associate_id' => ['nullable', 'different:leader_id', Rule::exists('users', 'id')],
            'module_key' => ['nullable', Rule::in(UserRole::allModules())],
        ])->validate();
        DB::transaction(function () use ($id, $leaderId, $associateId, $module, $instrumentAssociateIds) {
            DB::table('tenants')->where('id', app(TenantContext::class)->requireId())->lockForUpdate()->first();
            $entry = ChoirCommission::lockForUpdate()->findOrFail($id);
            $code = $entry->archive_commission ? Commission::from($entry->archive_commission) : null;
            $effectiveModule = $code?->module() ?? $module;
            $ids = $instrumentAssociateIds ?? $entry->instrumentAssociates()->pluck('users.id')->all();
            Validator::make(['instrument_associate_ids' => $ids], [
                'instrument_associate_ids' => 'array|max:20',
                'instrument_associate_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id'), Rule::notIn(array_filter([$leaderId]))],
            ])->validate();
            if ($effectiveModule === UserRole::MODULE_INSTRUMENTS && $ids && ! $leaderId) {
                throw ValidationException::withMessages(['leader_id' => 'Désignez le responsable avant ses associés.']);
            }
            foreach ($effectiveModule === UserRole::MODULE_INSTRUMENTS ? $ids : [] as $userId) {
                $associate = User::lockForUpdate()->findOrFail($userId);
                if ($associate->member_id && ! $associate->member()->exists()) {
                    throw ValidationException::withMessages(['instrument_associate_ids' => 'Restaurez la fiche de ce choriste avant de lui déléguer cet accès.']);
                }
            }
            if ($leaderId) {
                $leader = User::lockForUpdate()->findOrFail($leaderId);
                if ($leader->member_id) {
                    if (! $leader->member()->exists()) {
                        throw ValidationException::withMessages(['leader_id' => 'Restaurez la fiche de ce choriste avant de lui déléguer une responsabilité.']);
                    }
                    $entry->members()->syncWithoutDetaching([$leader->member_id]);
                }
            }
            $entry->update(['leader_id' => $leaderId, 'associate_id' => $code?->hasPrivateArchive() ? $associateId : null, 'module_key' => $code?->module() ?? $module]);
            $entry->instrumentAssociates()->sync($effectiveModule === UserRole::MODULE_INSTRUMENTS ? $ids : []);
            if ($code?->hasPrivateArchive()) {
                CommissionTeam::updateOrCreate(['commission' => $code->value], ['leader_id' => $leaderId, 'associate_id' => $associateId]);
            }
        }, 3);
        app(CommissionAccess::class)->forget();
    }
}
