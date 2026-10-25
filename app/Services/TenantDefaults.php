<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CommissionTeam;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class TenantDefaults
{
    public function ensure(): void
    {
        $tenant = app(TenantContext::class)->requireId();
        DB::transaction(function () use ($tenant) {
            FinancialCatalog::seed($tenant);
            foreach ([['501', 'Caisse de la chorale', 'tresorerie'], ['502', 'Caisse des offrandes', 'tresorerie'], ['503', 'Compte bancaire', 'tresorerie'], ['61', 'Achats de partitions', 'charge'], ['62', 'Maintenance des instruments', 'charge'], ['63', 'Location de salle', 'charge'], ['64', 'Frais de déplacement', 'charge'], ['65', 'Frais divers', 'charge'], ['71', 'Produits des concerts', 'produit'], ['72', 'Cotisations des choristes', 'produit'], ['73', 'Subventions et dons', 'produit'], ['74', 'Offrandes', 'produit']] as [$code, $name, $type]) {
                Account::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
            }
            foreach (['commission_liturgique', 'commission_secretariat'] as $commission) {
                CommissionTeam::firstOrCreate(['commission' => $commission]);
            }
            app(CommissionCatalog::class)->ensure();
            DB::table('operation_locks')->insertOrIgnore(['tenant_id' => $tenant, 'key' => 'accounts']);
        });
    }
}
