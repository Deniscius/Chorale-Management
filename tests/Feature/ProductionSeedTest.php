<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\FinancialType;
use App\Models\Instrument;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\InstrumentIdentity;
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

    public function test_existing_business_rows_are_preserved_but_instrument_backfill_is_a_write(): void
    {
        config(['tenancy.enabled' => true]);
        $user = app(TenantRegistration::class)->create(['organization' => 'Migration', 'name' => 'Responsable', 'username' => 'migration', 'email' => 'migration@example.test', 'password' => 'TestOnlySecret2026!']);
        $instrument = app(TenantContext::class)->run($user->tenant_id, function () {
            Transaction::factory()->recette()->create(['amount' => '12345.67']);
            Donation::factory()->create(['amount' => '2500.50']);
            Contribution::factory()->create(['member_id' => Member::factory()->create()->id, 'paid_amount' => 1000]);
            $instrument = Instrument::factory()->create(['quantity' => 2]);
            app(InstrumentIdentity::class)->synchronize($instrument->id);
            $instrument->update(['quantity' => 3]);

            return $instrument;
        });
        DB::table('tenant_licenses')->insert(['tenant_id' => $user->tenant_id, 'license_hash' => hash('sha256', 'test-only-license'), 'provider_id' => 'test-only-provider', 'expires_at' => now()->addMonth(), 'status' => 'active', 'sync_status' => 'verified']);
        $tables = ['tenants', 'users', 'choirs', 'members', 'transactions', 'donations', 'contributions', 'tenant_licenses', 'instruments'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $units = DB::table('instrument_units')->orderBy('id')->get();
        $auditCount = DB::table('audit_events')->count();
        app()->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        foreach ($units as $unit) {
            $this->assertEquals($unit, DB::table('instrument_units')->where('id', $unit->id)->first());
        }
        $this->assertSame(3, DB::table('instrument_units')->where('instrument_id', $instrument->id)->count());
        $this->assertSame($auditCount + 1, DB::table('audit_events')->count());
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame($auditCount + 1, DB::table('audit_events')->count());
    }
}
