<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantDefaults;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Only existing customer spaces: never create customers or credentials.
        Tenant::whereIn('id', DB::table('users')->select('tenant_id')->distinct())
            ->orderBy('id')->eachById(function (Tenant $tenant) {
                app(TenantContext::class)->run($tenant->id, fn () => app(TenantDefaults::class)->ensure());
            });
    }
}
