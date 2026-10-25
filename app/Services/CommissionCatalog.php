<?php

namespace App\Services;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class CommissionCatalog
{
    public function ensure(): void
    {
        // A tenant lock makes first-time reference creation safe under concurrent setup.
        DB::transaction(function () {
            DB::table('tenants')->where('id', app(TenantContext::class)->requireId())->lockForUpdate()->first();
            foreach (Commission::cases() as $code) {
                if (ChoirCommission::where('archive_commission', $code->value)->exists()) {
                    continue;
                }
                $name = $code->label();
                $suffix = 1;
                while (ChoirCommission::where('name_key', mb_strtolower($name))->exists()) {
                    $name = $code->label().' · '.++$suffix;
                }
                $team = CommissionTeam::where('commission', $code->value)->first();
                $leaderId = $team?->leader_id;
                if ($team && $code->hasPrivateArchive()) {
                    $requiredRole = $code === Commission::Liturgique ? UserRole::CommissionLiturgique : UserRole::Secretaire;
                    if (User::find($leaderId)?->role !== $requiredRole) {
                        $leaderId = null;
                    }
                }
                $entry = ChoirCommission::create([
                    'name' => $name, 'name_key' => mb_strtolower($name), 'kind' => 'permanent',
                    'mission' => 'Coordonner les missions de la '.$code->label().' au sein de la chorale.',
                    'starts_on' => today(), 'module_key' => $code->module(), 'archive_commission' => $code->value,
                    'leader_id' => $leaderId, 'associate_id' => $team?->associate_id,
                ]);
                $role = match ($code) {
                    Commission::Liturgique => UserRole::CommissionLiturgique,
                    Commission::Instruments => UserRole::CommissionInstruments,
                    Commission::VieCulturelle => UserRole::CommissionVieCulturelle,
                    Commission::SuivieFormation => UserRole::CommissionSuivieFormation,
                    Commission::Secretariat => UserRole::Secretaire,
                    Commission::Tresorerie => UserRole::Tresorier,
                    Commission::Direction => UserRole::Directeur,
                };
                // Import the existing membership once; never re-add a person removed later.
                $ids = Member::where('commission', $code->value)->orWhereHas('user', fn ($q) => $q->where('role', $role))->pluck('id');
                $entry->members()->sync($ids);
            }
        }, 3);
    }
}
