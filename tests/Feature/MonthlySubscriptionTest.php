<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChariowLicense;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true, 'tenancy.chariow.api_key' => 'fake-api-never-real', 'tenancy.chariow.product_id' => 'prd_monthly']);
        Http::preventStrayRequests();
    }

    private function fake(array $responses): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function space(string $name = 'alpha'): array
    {
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'SecretChorale2026']);
        app(TenantContext::class)->run($user->tenant_id, fn () => $user->forceFill(['email_verified_at' => now()])->save());
        $tenant = Tenant::findOrFail($user->tenant_id);
        $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

        return [$tenant, $user];
    }

    private function license(User $user, array $overrides = []): array
    {
        return array_replace_recursive(['id' => 'license_alpha', 'status' => 'active', 'is_active' => true, 'is_expired' => false, 'revoked_at' => null,
            'expires_at' => now()->addMonthNoOverflow()->startOfSecond()->toISOString(),
            'product' => ['id' => 'prd_monthly', 'pricing' => ['price' => ['value' => 2000, 'currency' => 'XOF']]],
            'customer' => ['email' => $user->email]], $overrides);
    }

    private function activate(Tenant $tenant, User $user): int
    {
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($user)])]);
        app(ChariowLicense::class)->activate($tenant, $user, 'SYNTHETIC-MONTHLY');

        return (int) DB::table('tenant_licenses')->value('id');
    }

    public function test_price_is_per_choir_on_signup_and_billing_without_automatic_trial_charge(): void
    {
        $this->get('/creer-un-espace')->assertOk()->assertSee('2 000 FCFA')->assertSee('/ mois / chorale')->assertSee('aucun prélèvement');
        [$tenant, $admin] = $this->space();
        $this->actingAs($admin)->get('/abonnement')->assertOk()->assertSee('2 000 FCFA')->assertSee('Un seul abonnement couvre');
        $this->assertNull($tenant->fresh()->paid_until);
        Http::assertNothingSent();
    }

    public function test_wrong_amount_currency_or_missing_price_never_grants_paid_access(): void
    {
        [$tenant, $admin] = $this->space();
        foreach ([['value' => 1000, 'currency' => 'XOF'], ['value' => 2000, 'currency' => 'XAF'], ['value' => true, 'currency' => 'XOF'], ['value' => '2000fake', 'currency' => 'XOF'], ['value' => 2000, 'currency' => null]] as $price) {
            $this->travel(61)->seconds();
            $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['product' => ['pricing' => ['price' => $price]]])])]);
            $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'FAKE'])->assertSessionHasErrors('license_key');
            $this->assertNull($tenant->fresh()->paid_until);
        }
        $this->assertDatabaseCount('tenant_licenses', 0);
    }

    public function test_public_id_sync_updates_the_provider_month_without_adding_arbitrary_days(): void
    {
        [$tenant, $admin] = $this->space();
        $id = $this->activate($tenant, $admin);
        $until = $tenant->fresh()->paid_until->addMonthNoOverflow();
        $this->fake(['api.chariow.com/v1/licenses/license_alpha' => Http::response(['data' => $this->license($admin, ['expires_at' => $until->toISOString()])])]);
        $this->assertTrue(app(ChariowLicense::class)->sync($id));
        $this->assertTrue(app(ChariowLicense::class)->sync($id));
        $this->assertTrue($tenant->fresh()->paid_until->equalTo($until));
        $this->assertDatabaseCount('tenant_licenses', 1);
        $this->assertSame(hash('sha256', 'SYNTHETIC-MONTHLY'), DB::table('tenant_licenses')->value('license_hash'));
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_ends_with($request->url(), '/license_alpha'));
    }

    public function test_expired_existing_subscription_recovers_after_a_verified_provider_renewal(): void
    {
        [$tenant, $admin] = $this->space();
        $id = $this->activate($tenant, $admin);
        $this->travelTo($tenant->fresh()->paid_until);
        $this->assertFalse($tenant->fresh()->hasAccess());
        $this->actingAs($admin)->get('/abonnement')->assertOk()->assertSee('Votre abonnement est à renouveler');
        $next = now()->addMonthNoOverflow()->startOfSecond();
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['expires_at' => $next->toISOString()])])]);
        $this->post('/abonnement/actualiser')->assertRedirect(route('billing.show'));
        $this->assertTrue($tenant->fresh()->hasAccess());
        $this->assertTrue($tenant->fresh()->paid_until->equalTo($next));
    }

    public function test_revocation_removes_paid_access_without_touching_another_choir(): void
    {
        [$tenant, $admin] = $this->space();
        [$other] = $this->space('beta');
        $other->forceFill(['paid_until' => now()->addYear()])->save();
        $id = $this->activate($tenant, $admin);
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['status' => 'revoked', 'is_active' => false, 'revoked_at' => now()->toISOString()])])]);
        $this->assertTrue(app(ChariowLicense::class)->sync($id));
        $this->assertFalse($tenant->fresh()->hasAccess());
        $this->assertTrue($other->fresh()->hasAccess());
        $this->assertDatabaseHas('tenant_licenses', ['id' => $id, 'status' => 'revoked', 'sync_status' => 'ok']);
    }

    public function test_provider_outage_keeps_only_the_already_paid_period_and_never_extends_it(): void
    {
        [$tenant, $admin] = $this->space();
        $id = $this->activate($tenant, $admin);
        $until = $tenant->fresh()->paid_until;
        $this->fake(['api.chariow.com/*' => Http::response(['secret' => 'NEVER_LOG_THIS'], 503)]);
        $this->assertFalse(app(ChariowLicense::class)->sync($id));
        $this->assertTrue($tenant->fresh()->paid_until->equalTo($until));
        $this->assertDatabaseHas('tenant_licenses', ['id' => $id, 'sync_status' => 'error']);
        $this->travelTo($until);
        $this->assertFalse($tenant->fresh()->hasAccess());
    }

    public function test_wrong_provider_identity_or_lifetime_response_never_extends_the_subscription(): void
    {
        [$tenant, $admin] = $this->space();
        $id = $this->activate($tenant, $admin);
        $until = $tenant->fresh()->paid_until;
        foreach ([['id' => 'license_foreign'], ['expires_at' => null], ['expires_at' => '2099-02-30T00:00:00Z']] as $invalid) {
            $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, $invalid)])]);
            $this->assertFalse(app(ChariowLicense::class)->sync($id));
            $this->assertTrue($tenant->fresh()->paid_until->equalTo($until));
        }
        $this->assertFalse($tenant->fresh()->paid_perpetual);
    }

    public function test_manual_refresh_is_admin_only_and_does_not_accept_another_tenant_identifier(): void
    {
        [$tenant, $admin] = $this->space();
        $this->space('beta');
        $member = app(TenantContext::class)->run($tenant->id, fn () => User::factory()->role(UserRole::Choriste)->create());
        $this->actingAs($member)->post('/abonnement/actualiser', ['tenant_id' => 999])->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($admin)->post('/abonnement/actualiser', ['tenant_id' => 999])->assertRedirect(route('billing.show'));
        Http::assertNothingSent();
    }

    public function test_sync_command_refuses_missing_configuration_without_network_calls(): void
    {
        config(['tenancy.chariow.api_key' => null]);
        $this->artisan('chorale:sync-subscriptions')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_scheduler_refreshes_oldest_records_and_stops_after_three_provider_failures(): void
    {
        [$tenant, $admin] = $this->space();
        $this->activate($tenant, $admin);
        DB::table('tenant_licenses')->update(['last_checked_at' => now()->subHour()]);
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin)])]);
        $this->artisan('chorale:sync-subscriptions', ['--limit' => 1])->assertSuccessful();
        $this->assertDatabaseHas('tenant_licenses', ['sync_status' => 'ok']);
        $this->travel(16)->minutes();
        foreach (range(2, 5) as $number) {
            DB::table('tenant_licenses')->insert(['tenant_id' => $tenant->id, 'license_hash' => hash('sha256', 'fake'.$number), 'provider_id' => 'license_'.$number, 'expires_at' => now()->addMonth(), 'last_checked_at' => now()->subHours($number), 'status' => 'active', 'sync_status' => 'ok']);
        }
        $this->fake(['api.chariow.com/*' => Http::response([], 503)]);
        $this->artisan('chorale:sync-subscriptions')->assertFailed();
        $this->assertSame(3, DB::table('tenant_licenses')->where('sync_status', 'error')->count());
        Http::assertSentCount(3);
    }

    public function test_a_license_with_the_second_buyer_email_cannot_move_to_another_choir(): void
    {
        [$tenant, $admin] = $this->space();
        [$other, $otherAdmin] = $this->space('beta');
        $this->activate($tenant, $admin);
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($otherAdmin)])]);
        $this->actingAs($otherAdmin)->post('/abonnement/activer', ['license_key' => 'SECOND-FAKE-KEY'])->assertSessionHasErrors('license_key');
        $this->assertNull($other->fresh()->paid_until);
        $this->assertDatabaseHas('tenant_licenses', ['provider_id' => 'license_alpha', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('tenant_licenses', 1);
    }

    public function test_revoking_one_license_preserves_another_valid_period_for_the_same_choir(): void
    {
        [$tenant, $admin] = $this->space();
        $id = $this->activate($tenant, $admin);
        $later = now()->addMonthsNoOverflow(2)->startOfSecond();
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['id' => 'license_second', 'expires_at' => $later->toISOString()])])]);
        app(ChariowLicense::class)->activate($tenant, $admin, 'SECOND-FAKE-KEY');
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['status' => 'revoked', 'is_active' => false, 'revoked_at' => now()->toISOString()])])]);
        $this->assertTrue(app(ChariowLicense::class)->sync($id));
        $this->assertTrue($tenant->fresh()->paid_until->equalTo($later));
        $this->assertTrue($tenant->fresh()->hasAccess());
    }

    public function test_missing_provider_price_is_rejected_and_never_creates_a_license(): void
    {
        [$tenant, $admin] = $this->space();
        $license = $this->license($admin);
        unset($license['product']['pricing']);
        $this->fake(['api.chariow.com/*' => Http::response(['data' => $license])]);
        $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'FAKE'])->assertSessionHasErrors('license_key');
        $this->assertDatabaseCount('tenant_licenses', 0);
        $this->assertNull($tenant->fresh()->paid_until);
    }

    public function test_manual_refresh_stops_on_the_first_outage_and_never_posts_a_payment(): void
    {
        [$tenant, $admin] = $this->space();
        $this->activate($tenant, $admin);
        $this->fake(['api.chariow.com/*' => Http::response([], 503)]);
        $this->actingAs($admin)->post('/abonnement/actualiser')->assertRedirect(route('billing.show'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }
}
