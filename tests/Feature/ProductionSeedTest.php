<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FinancialType;
use App\Models\Tenant;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_production_has_no_fake_customers_or_credentials(): void
    {
        app()->instance('env', 'production');
        $counts = collect(['tenants', 'users', 'members', 'transactions', 'donations', 'contributions'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    public function test_production_defaults_are_idempotent_and_preserve_customer_configuration(): void
    {
        config(['tenancy.enabled' => true]);
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale réelle', 'name' => 'Responsable', 'username' => 'responsable', 'email' => 'real@example.test', 'password' => 'RealSecret2026!']);
        $password = $user->password;
        app(TenantContext::class)->run($user->tenant_id, function () {
            Account::where('code', '501')->update(['name' => 'Ma caisse', 'is_active' => false]);
            FinancialType::where('code', 'mensuelle')->update(['label' => 'Participation', 'label_key' => 'participation', 'default_amount' => 3500, 'is_active' => false]);
        });
        $counts = collect(['accounts', 'commission_teams', 'financial_types', 'users', 'tenants'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $tenantBefore = Tenant::findOrFail($user->tenant_id)->getRawOriginal();
        app()->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($tenantBefore, Tenant::findOrFail($user->tenant_id)->getRawOriginal());
        $this->assertDatabaseHas('accounts', ['tenant_id' => $user->tenant_id, 'code' => '501', 'name' => 'Ma caisse', 'is_active' => false]);
        $this->assertDatabaseHas('financial_types', ['tenant_id' => $user->tenant_id, 'code' => 'mensuelle', 'label' => 'Participation', 'default_amount' => 3500, 'is_active' => false]);
    }

    public function test_all_demo_seeders_refuse_direct_production_execution(): void
    {
        app()->instance('env', 'production');
        foreach (['Database', 'AdminUser', 'Accounting', 'Activity', 'Choir', 'Correspondence', 'Instruments', 'Liturgical'] as $name) {
            $class = 'Database\\Seeders\\'.$name.'Seeder';
            try {
                (new $class)->run();
                $this->fail($class.' accepted production');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('production', $exception->getMessage());
            }
        }
    }
}
