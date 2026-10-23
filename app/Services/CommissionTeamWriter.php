<?php

namespace App\Services;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\CommissionTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CommissionTeamWriter
{
    public function save(Commission $commission, ?int $leaderId, ?int $associateId): CommissionTeam
    {
        abort_unless($commission->hasPrivateArchive(), 404);

        return DB::transaction(function () use ($commission, $leaderId, $associateId) {
            $team = CommissionTeam::query()->lockForUpdate()->where('commission', $commission->value)->firstOrFail();
            $user = auth()->user();
            $isAdmin = $user?->isAdmin();
            abort_unless($isAdmin || ($team->leader_id === auth()->id() && $team->allows($user)), 403);
            if (! $isAdmin && $leaderId !== $team->leader_id) {
                abort(403);
            }
            $role = $commission === Commission::Liturgique ? UserRole::CommissionLiturgique : UserRole::Secretaire;
            Validator::make(['leader_id' => $leaderId, 'associate_id' => $associateId], [
                'leader_id' => ['required', Rule::exists('users', 'id')->where('role', $role->value)],
                'associate_id' => ['nullable', 'different:leader_id', Rule::exists('users', 'id')],
            ])->validate();
            $team->fill(['leader_id' => $leaderId, 'associate_id' => $associateId])->save();

            return $team;
        }, 3);
    }
}
