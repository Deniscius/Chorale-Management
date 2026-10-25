<?php

namespace App\Services;

use App\Enums\Commission;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CommissionTeamWriter
{
    public function save(Commission $commission, ?int $leaderId, ?int $associateId): CommissionTeam
    {
        abort_unless($commission->hasPrivateArchive(), 404);

        return DB::transaction(function () use ($commission, $leaderId, $associateId) {
            DB::table('tenants')->where('id', app(TenantContext::class)->requireId())->lockForUpdate()->first();
            $team = CommissionTeam::query()->lockForUpdate()->where('commission', $commission->value)->firstOrFail();
            $user = auth()->user();
            $isAdmin = $user?->isAdmin();
            abort_unless($isAdmin || ($team->leader_id === auth()->id() && $team->allows($user)), 403);
            if (! $isAdmin && $leaderId !== $team->leader_id) {
                abort(403);
            }
            Validator::make(['leader_id' => $leaderId, 'associate_id' => $associateId], [
                'leader_id' => ['required', Rule::exists('users', 'id')],
                'associate_id' => ['nullable', 'different:leader_id', Rule::exists('users', 'id')],
            ])->validate();
            if ($isAdmin) {
                app(CommissionCatalog::class)->ensure();
                $official = ChoirCommission::where('archive_commission', $commission->value)->firstOrFail();
                app(CommissionResponsibilities::class)->assign($official->id, $leaderId, $associateId, $commission->module());

                return $team->fresh();
            }
            $team->fill(['leader_id' => $leaderId, 'associate_id' => $associateId])->save();
            ChoirCommission::where('archive_commission', $commission->value)->first()?->update(['associate_id' => $associateId]);

            return $team;
        }, 3);
    }
}
