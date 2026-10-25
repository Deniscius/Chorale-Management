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
    public function assign(int $id, ?int $leaderId, ?int $associateId, ?string $module): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        Validator::make(['leader_id' => $leaderId, 'associate_id' => $associateId, 'module_key' => $module], [
            'leader_id' => ['nullable', Rule::exists('users', 'id')],
            'associate_id' => ['nullable', 'different:leader_id', Rule::exists('users', 'id')],
            'module_key' => ['nullable', Rule::in(UserRole::allModules())],
        ])->validate();
        DB::transaction(function () use ($id, $leaderId, $associateId, $module) {
            DB::table('tenants')->where('id', app(TenantContext::class)->requireId())->lockForUpdate()->first();
            $entry = ChoirCommission::lockForUpdate()->findOrFail($id);
            $code = $entry->archive_commission ? Commission::from($entry->archive_commission) : null;
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
            if ($code?->hasPrivateArchive()) {
                CommissionTeam::updateOrCreate(['commission' => $code->value], ['leader_id' => $leaderId, 'associate_id' => $associateId]);
            }
        }, 3);
        app(CommissionAccess::class)->forget();
    }
}
