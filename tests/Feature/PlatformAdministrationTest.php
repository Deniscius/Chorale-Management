<?php

namespace Tests\Feature;

use App\Models\Platform\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\Totp;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private const PASSWORD = 'OwnerSecretPassword2026';

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
    }

    private function owner(): Admin
    {
        return Admin::create(['name' => 'Propriétaire', 'email' => 'owner@example.test', 'password' => self::PASSWORD, 'totp_secret' => self::SECRET]);
    }

    private function loginOwner(?Admin $admin = null): Admin
    {
        $admin ??= $this->owner();
        $this->post('/plateforme/connexion', ['email' => $admin->email, 'password' => self::PASSWORD, 'code' => app(Totp::class)->code(self::SECRET, intdiv(now()->timestamp, 30))])->assertRedirect('/plateforme');

        return $admin;
    }

    private function client(string $name = 'alpha'): array
    {
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'SecretChorale2026']);

        return [Tenant::findOrFail($user->tenant_id), $user];
    }

    public function test_owner_login_requires_password_and_totp_and_encrypts_secret(): void
    {
        $admin = $this->owner();
        $this->assertNotSame(self::SECRET, DB::table('platform_admins')->value('totp_secret'));
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->post('/plateforme/connexion', ['email' => $admin->email, 'password' => self::PASSWORD, 'code' => 'wrong'])->assertSessionHasErrors('code');
        $this->assertGuest('platform');
        $this->loginOwner($admin);
        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertGuest('web');
        $this->get('/plateforme')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('platform_audit_events', ['platform_admin_id' => $admin->id, 'action' => 'login']);
    }

    public function test_totp_cannot_be_replayed_after_logout(): void
    {
        $admin = $this->loginOwner();
        $this->post('/plateforme/deconnexion')->assertRedirect('/plateforme/connexion');
        $this->post('/plateforme/connexion', ['email' => $admin->email, 'password' => self::PASSWORD, 'code' => app(Totp::class)->code(self::SECRET, intdiv(now()->timestamp, 30))])->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_tenant_admin_cannot_enter_platform_and_owner_cannot_enter_business(): void
    {
        [$tenant, $user] = $this->client();
        $this->actingAs($user)->get('/plateforme')->assertRedirect('/plateforme/connexion');
        $this->get('/plateforme/chorales/'.$tenant->id)->assertRedirect('/plateforme/connexion');
        Auth::guard('web')->logout();
        $this->loginOwner();
        $this->get('/membres')->assertRedirect('/login');
        $this->assertSame(0, User::count());
        $this->assertNull(app(TenantContext::class)->id());
    }

    public function test_platform_guard_without_mfa_never_grants_access(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'platform')->get('/plateforme')->assertRedirect('/plateforme/connexion');
    }

    public function test_suspended_client_session_does_not_block_owner_portal(): void
    {
        [$tenant, $user] = $this->client();
        $tenant->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($user);
        $this->loginOwner();
        $this->get('/plateforme')->assertOk();
        $this->get('/plateforme/chorales/'.$tenant->id)->assertOk()->assertSee('Suspendu');
        $this->get('/dashboard')->assertRedirect('/abonnement');
        $this->assertNull(app(TenantContext::class)->id());
    }

    public function test_disabled_owner_and_expired_session_are_rejected(): void
    {
        $admin = $this->loginOwner();
        $this->travel(9)->hours();
        $this->get('/plateforme')->assertRedirect('/plateforme/connexion');
        $this->travelBack();
        $admin->forceFill(['enabled' => false])->save();
        $this->post('/plateforme/connexion', ['email' => $admin->email, 'password' => self::PASSWORD, 'code' => app(Totp::class)->code(self::SECRET, intdiv(now()->timestamp, 30))])->assertSessionHasErrors('email');
    }

    public function test_credential_reset_revokes_an_existing_session(): void
    {
        $admin = $this->loginOwner();
        $admin->forceFill(['password' => 'AnotherStrongPassword2026'])->save();
        Auth::guard('platform')->forgetUser();
        $this->get('/plateforme')->assertRedirect('/plateforme/connexion');
    }

    public function test_login_throttles_shared_account_across_ips_and_never_flashes_codes(): void
    {
        $admin = $this->owner();
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($i + 1)])->post('/plateforme/connexion', ['email' => $admin->email, 'password' => 'incorrect', 'code' => '123456'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->post('/plateforme/connexion', ['email' => $admin->email, 'password' => self::PASSWORD, 'code' => app(Totp::class)->code(self::SECRET, intdiv(now()->timestamp, 30))])->assertSessionHasErrors('email');
        $this->assertGuest('platform');
        $this->assertArrayNotHasKey('code', session()->getOldInput());
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_dashboard_counts_disjoint_statuses_and_filters_clients(): void
    {
        [$trial] = $this->client('trial');
        [$paid] = $this->client('paid');
        [$expired] = $this->client('expired');
        [$suspended] = $this->client('suspended');
        $paid->forceFill(['paid_until' => now()->addMonth()])->save();
        $expired->forceFill(['trial_ends_at' => now()])->save();
        $suspended->forceFill(['suspended_at' => now()])->save();
        $this->loginOwner();
        $this->get('/plateforme')->assertOk()->assertViewHas('stats', fn ($stats) => (int) $stats->total === 4 && (int) $stats->trial === 1 && (int) $stats->paid === 1 && (int) $stats->expired === 1 && (int) $stats->suspended === 1);
        $this->get('/plateforme/chorales?status=expired')->assertOk()->assertSee('Chorale expired')->assertDontSee('Chorale trial');
        $this->get('/plateforme/chorales?q=paid')->assertOk()->assertSee('Chorale paid')->assertDontSee('Chorale trial');
    }

    public function test_suspend_resume_and_extension_are_audited_and_do_not_create_payment(): void
    {
        [$tenant] = $this->client();
        $admin = $this->loginOwner();
        $url = '/plateforme/chorales/'.$tenant->id;
        $this->post($url, ['action' => 'suspend', 'reason' => 'Demande de suspension', 'current_password' => self::PASSWORD])->assertRedirect($url);
        $this->assertFalse($tenant->fresh()->hasAccess());
        $this->assertDatabaseHas('platform_audit_events', ['platform_admin_id' => $admin->id, 'tenant_id' => $tenant->id, 'action' => 'suspend', 'reason' => 'Demande de suspension']);
        $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->post($url, ['action' => 'extend_trial', 'days' => 3, 'reason' => 'Essai commercial', 'current_password' => self::PASSWORD])->assertSessionHasErrors('action');
        $this->post($url, ['action' => 'resume', 'reason' => 'Fin de suspension', 'current_password' => self::PASSWORD])->assertRedirect($url);
        $this->assertFalse($tenant->fresh()->hasAccess());
        $this->post($url, ['action' => 'extend_trial', 'days' => 3, 'reason' => 'Essai commercial', 'current_password' => self::PASSWORD])->assertRedirect($url);
        $this->assertTrue($tenant->fresh()->hasAccess());
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $tenant->fresh()->trial_ends_at->timestamp, 2);
        $this->assertNull($tenant->fresh()->paid_until);
        $this->assertFalse($tenant->fresh()->paid_perpetual);
        $this->get('/plateforme/journal')->assertOk()->assertSee('Essai commercial')->assertSee('Prolongation');
    }

    public function test_access_mutations_require_password_reason_and_bounded_days(): void
    {
        [$tenant] = $this->client();
        $this->loginOwner();
        $url = '/plateforme/chorales/'.$tenant->id;
        $this->post($url, ['action' => 'suspend', 'reason' => 'Test valide', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->post($url, ['action' => 'suspend', 'current_password' => self::PASSWORD])->assertSessionHasErrors('reason');
        $this->post($url, ['action' => 'extend_trial', 'days' => 31, 'reason' => 'Test valide', 'current_password' => self::PASSWORD])->assertSessionHasErrors('days');
        $this->assertNull($tenant->fresh()->suspended_at);
        $this->assertDatabaseCount('platform_audit_events', 1);
        $this->assertArrayNotHasKey('current_password', session()->getOldInput());
    }

    public function test_unauthorized_mutation_never_changes_an_access_or_discloses_tenant_existence(): void
    {
        [$tenant, $user] = $this->client();
        $this->actingAs($user)->post('/plateforme/chorales/'.$tenant->id, ['action' => 'suspend'])->assertRedirect('/plateforme/connexion');
        $this->get('/plateforme/chorales/999999')->assertRedirect('/plateforme/connexion');
        $this->assertNull($tenant->fresh()->suspended_at);
        $this->assertDatabaseCount('platform_audit_events', 0);
    }

    public function test_usage_is_aggregated_without_recording_private_content_or_owner_requests(): void
    {
        [$tenant, $user] = $this->client();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->get('/membres')->assertOk();
        $this->assertDatabaseHas('platform_usage_daily', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'requests' => 2, 'errors' => 0]);
        $this->loginOwner();
        $this->get('/plateforme/supervision')->assertOk()->assertSee('2')->assertSee('Connexion réussie');
        $this->get('/plateforme/chorales/'.$tenant->id)->assertOk();
        $this->assertSame(2, (int) DB::table('platform_usage_daily')->sum('requests'));
    }

    public function test_validation_errors_are_not_recorded_as_server_failures(): void
    {
        [$tenant, $user] = $this->client();
        Route::middleware('web')->post('/test-platform-validation', function () {
            throw ValidationException::withMessages(['field' => 'Invalid']);
        });
        $this->actingAs($user)->post('/test-platform-validation')->assertSessionHasErrors('field');
        $this->assertDatabaseHas('platform_usage_daily', ['tenant_id' => $tenant->id, 'requests' => 1, 'errors' => 0]);
    }

    public function test_platform_routes_except_login_and_initial_setup_use_dedicated_middleware(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'platform.') && ! in_array($route->getName(), ['platform.login', 'platform.login.store', 'platform.setup', 'platform.setup.store'], true)) {
                $this->assertContains('platform.auth', $route->middleware(), $route->getName());
            }
        }
    }

    public function test_console_provisioning_requires_a_confirmed_authenticator_and_logs_creation(): void
    {
        $code = app(Totp::class)->code(self::SECRET, intdiv(now()->timestamp, 30));
        $this->partialMock(Totp::class, fn ($mock) => $mock->shouldReceive('secret')->once()->andReturn(self::SECRET));
        $this->artisan('platform:admin', ['email' => 'owner@example.test', '--name' => 'Propriétaire'])
            ->expectsQuestion('Mot de passe (16 caractères minimum, lettres et chiffres)', self::PASSWORD)
            ->expectsQuestion('Confirmez le mot de passe', self::PASSWORD)
            ->expectsQuestion('Code affiché par votre application pour confirmer la configuration', $code)
            ->assertSuccessful();
        $admin = Admin::firstOrFail();
        $this->assertSame(self::SECRET, $admin->totp_secret);
        $this->assertDatabaseHas('platform_audit_events', ['platform_admin_id' => $admin->id, 'action' => 'admin_created']);
    }

    public function test_invalid_authenticator_does_not_provision_an_owner(): void
    {
        $this->artisan('platform:admin', ['email' => 'owner@example.test', '--name' => 'Propriétaire'])
            ->expectsQuestion('Mot de passe (16 caractères minimum, lettres et chiffres)', self::PASSWORD)
            ->expectsQuestion('Confirmez le mot de passe', self::PASSWORD)
            ->expectsQuestion('Code affiché par votre application pour confirmer la configuration', 'invalid')
            ->assertFailed();
        $this->assertDatabaseCount('platform_admins', 0);
        $this->assertDatabaseCount('platform_audit_events', 0);
    }

    public function test_console_recovery_changes_credentials_and_revokes_existing_session(): void
    {
        $admin = $this->loginOwner();
        $newSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $code = app(Totp::class)->code($newSecret, intdiv(now()->timestamp, 30));
        $this->partialMock(Totp::class, fn ($mock) => $mock->shouldReceive('secret')->once()->andReturn($newSecret));
        $this->artisan('platform:admin', ['email' => $admin->email, '--name' => $admin->name, '--reset' => true])
            ->expectsQuestion('Mot de passe (16 caractères minimum, lettres et chiffres)', 'RecoveredStrongPassword2026')
            ->expectsQuestion('Confirmez le mot de passe', 'RecoveredStrongPassword2026')
            ->expectsConfirmation('Réinitialiser ce compte et invalider ses sessions ?', 'yes')
            ->expectsQuestion('Code affiché par votre application pour confirmer la configuration', $code)
            ->assertSuccessful();
        $this->assertSame($newSecret, $admin->fresh()->totp_secret);
        $this->assertDatabaseHas('platform_audit_events', ['platform_admin_id' => $admin->id, 'action' => 'credentials_reset']);
        Auth::guard('platform')->forgetUser();
        $this->get('/plateforme')->assertRedirect('/plateforme/connexion');
    }
}
