<?php

namespace Tests\Feature;

use App\Models\Platform\Admin;
use App\Services\Platform\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformSetupTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'TestOnlyPrivateSetupTokenWithAtLeast32Characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['deployment.bootstrap_enabled' => true, 'deployment.setup_token' => self::TOKEN]);
    }

    private function form(): array
    {
        $this->get('/plateforme/installation')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $secret = Crypt::decryptString(session('platform_setup_secret'));

        return ['setup_token' => self::TOKEN, 'name' => 'Propriétaire', 'email' => 'owner@example.com', 'password' => 'UniqueOwnerPassword2026!', 'password_confirmation' => 'UniqueOwnerPassword2026!', 'code' => app(Totp::class)->code($secret, intdiv(now()->timestamp, 30))];
    }

    public function test_login_guides_first_owner_to_setup_and_boot_does_not_require_plaintext_credentials(): void
    {
        $this->get('/plateforme/connexion')->assertRedirect('/plateforme/installation');
        config(['deployment.bootstrap' => []]);
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('platform_admins', 0);
        $this->get('/login')->assertOk()->assertDontSee('Démonstration gratuite.');
    }

    public function test_private_token_password_confirmation_and_valid_mfa_are_required(): void
    {
        $data = $this->form();
        $this->post('/plateforme/installation', array_replace($data, ['setup_token' => str_repeat('x', 40)]))->assertSessionHasErrors('setup_token');
        $this->assertArrayNotHasKey('setup_token', session()->getOldInput());
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('code', session()->getOldInput());
        $this->post('/plateforme/installation', array_replace($data, ['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->post('/plateforme/installation', array_replace($data, ['code' => 'abcdef']))->assertSessionHasErrors('code');
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_first_owner_is_created_once_with_encrypted_mfa_and_then_setup_closes(): void
    {
        $data = $this->form();
        $secret = Crypt::decryptString(session('platform_setup_secret'));
        $this->post('/plateforme/installation', $data)->assertRedirect('/plateforme/connexion');
        $admin = Admin::firstOrFail();
        $this->assertTrue(password_verify($data['password'], $admin->password));
        $this->assertSame($secret, $admin->totp_secret);
        $this->assertNotSame($secret, DB::table('platform_admins')->value('totp_secret'));
        $this->assertFalse(session()->has('platform_setup_secret'));
        $this->assertGuest('platform');
        $this->get('/plateforme/installation')->assertNotFound();
        $this->post('/plateforme/installation', $data)->assertNotFound();
        $this->assertDatabaseCount('platform_admins', 1);
        $this->assertDatabaseCount('platform_audit_events', 1);
        config(['deployment.setup_token' => null, 'deployment.bootstrap' => []]);
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->travel(31)->seconds();
        $this->post('/plateforme/connexion', ['email' => $admin->email, 'password' => $data['password'], 'code' => app(Totp::class)->code($secret, intdiv(now()->timestamp, 30))])->assertRedirect('/plateforme');
        $this->get('/plateforme/supervision')->assertOk();
    }

    public function test_setup_is_unavailable_without_a_strong_configured_token(): void
    {
        config(['deployment.setup_token' => 'short']);
        $this->get('/plateforme/installation')->assertStatus(503);
        config(['deployment.setup_token' => self::TOKEN, 'deployment.bootstrap_enabled' => false]);
        $this->get('/plateforme/installation')->assertStatus(503);
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_expired_or_missing_mfa_session_never_creates_an_owner(): void
    {
        $data = $this->form();
        $this->travel(11)->minutes();
        $this->post('/plateforme/installation', $data)->assertSessionHasErrors('code');
        $this->post('/plateforme/installation', $data)->assertSessionHasErrors('code');
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_setup_attempts_are_rate_limited(): void
    {
        $data = $this->form();
        $data['setup_token'] = str_repeat('x', 40);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/plateforme/installation', $data)->assertSessionHasErrors('setup_token');
        }
        $this->post('/plateforme/installation', $data)->assertTooManyRequests();
        $this->assertDatabaseCount('platform_admins', 0);
    }
}
