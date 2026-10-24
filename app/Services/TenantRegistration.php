<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Choir;
use App\Models\CommissionTeam;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantRegistration
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = new Tenant(['name' => $data['organization'], 'slug' => Str::slug($data['organization']).'-'.Str::lower(Str::random(10))]);
            $tenant->forceFill(['trial_started_at' => now(), 'trial_ends_at' => now()->addHours(72)])->save();

            return app(TenantContext::class)->run($tenant->id, function () use ($data) {
                $user = User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'], 'role' => UserRole::Admin]);
                Choir::create(['name' => $data['organization'], 'slug' => 'principale', 'is_active' => true]);
                Setting::setMany(['chorale.name' => $data['organization']]);
                foreach ([['501', 'Caisse de la chorale', 'tresorerie'], ['502', 'Caisse des offrandes', 'tresorerie'], ['503', 'Compte bancaire', 'tresorerie'], ['61', 'Achats de partitions', 'charge'], ['62', 'Maintenance des instruments', 'charge'], ['63', 'Location de salle', 'charge'], ['64', 'Frais de déplacement', 'charge'], ['65', 'Frais divers', 'charge'], ['71', 'Produits des concerts', 'produit'], ['72', 'Cotisations des choristes', 'produit'], ['73', 'Subventions et dons', 'produit'], ['74', 'Offrandes', 'produit']] as [$code, $name, $type]) {
                    Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true]);
                }
                foreach (['commission_liturgique', 'commission_secretariat'] as $commission) {
                    CommissionTeam::create(['commission' => $commission]);
                }
                DB::table('operation_locks')->insert(['tenant_id' => app(TenantContext::class)->requireId(), 'key' => 'accounts']);

                return $user;
            });
        });
    }
}
