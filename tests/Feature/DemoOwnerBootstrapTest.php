<?php

namespace Tests\Feature;

use App\Models\Platform\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoOwnerBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['deployment.bootstrap_enabled' => true, 'deployment.bootstrap' => ['email' => 'owner@example.com', 'password' => 'StrongPersonalSecret2026!', 'totp_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP']]);
    }

    public function test_bootstrap_requires_explicit_enablement(): void
    {
        $this->configure();
        config(['deployment.bootstrap_enabled' => false]);
        $this->artisan('platform:bootstrap')->assertFailed();
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_initial_owner_has_hashed_password_encrypted_mfa_and_is_never_reset(): void
    {
        $this->configure();
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $admin = Admin::firstOrFail();
        $this->assertTrue(password_verify('StrongPersonalSecret2026!', $admin->password));
        $this->assertSame('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', $admin->totp_secret);
        $this->assertNotSame($admin->totp_secret, DB::table('platform_admins')->value('totp_secret'));
        $original = $admin->getRawOriginal();
        config(['deployment.bootstrap' => ['email' => 'different@example.com', 'password' => 'ChangedPersonalSecret2026!', 'totp_secret' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA']]);
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('platform_admins', 1);
        $this->assertSame($original, $admin->fresh()->getRawOriginal());
        config(['deployment.bootstrap' => []]);
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->assertDatabaseCount('platform_audit_events', 1);
    }

    public function test_invalid_bootstrap_never_creates_an_owner(): void
    {
        $this->configure();
        config(['deployment.bootstrap.password' => 'password', 'deployment.bootstrap.totp_secret' => 'bad']);
        $this->artisan('platform:bootstrap')->assertFailed();
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_public_pages_have_no_demo_branding(): void
    {
        config(['deployment.bootstrap_enabled' => true]);
        $this->get('/login')->assertOk()->assertDontSee('Démonstration gratuite.');
        config(['deployment.bootstrap_enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('Démonstration gratuite.');
    }
}
