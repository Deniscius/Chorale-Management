<?php

namespace App\Services;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChoirMasterAssociates
{
    public function authorizeHead(int $commissionId): ChoirCommission
    {
        $commission = ChoirCommission::query()->findOrFail($commissionId);

        abort_unless(
            $commission->status === 'active'
            && $commission->isChoirMasterCommission()
            && (auth()->user()?->isAdmin() || (int) $commission->leader_id === (int) auth()->id()),
            403
        );

        return $commission;
    }

    /** @param array<int, int|string> $associateIds */
    public function sync(int $commissionId, array $associateIds): void
    {
        DB::transaction(function () use ($commissionId, $associateIds): void {
            app(TenantContext::class)->lockForAccountChanges();
            $commission = ChoirCommission::query()->lockForUpdate()->findOrFail($commissionId);
            $this->authorizeHead($commissionId);

            $ids = array_map('intval', $associateIds);
            Validator::make(['ids' => $ids], [
                'ids' => ['array', 'max:20'],
                'ids.*' => ['required', 'integer', 'distinct',
                    Rule::exists('users', 'id')->where('tenant_id', app(TenantContext::class)->requireId()),
                    Rule::notIn(array_filter([$commission->leader_id]))],
            ])->validate();

            foreach ($ids as $userId) {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                if ($user->member_id && ! $user->member()->exists()) {
                    throw ValidationException::withMessages(['associateIds' => 'Un choriste désigné doit disposer d’une fiche valide.']);
                }
                if ($user->member_id) {
                    $commission->members()->syncWithoutDetaching([$user->member_id]);
                }
            }

            $before = $commission->choirMasters()->orderBy('users.id')->pluck('users.id')->all();
            $after = collect($ids)->sort()->values()->all();
            $commission->choirMasters()->sync($after);

            if ($before !== $after) {
                AuditEvent::create([
                    'actor_id' => auth()->id(),
                    'subject_type' => ChoirCommission::class,
                    'subject_id' => $commission->id,
                    'action' => 'updated',
                    'changes' => [
                        'before' => ['choir_master_ids' => $before],
                        'after' => ['choir_master_ids' => $after],
                    ],
                    'created_at' => now(),
                ]);
            }
        }, 3);

        app(CommissionAccess::class)->forget();
    }
}
