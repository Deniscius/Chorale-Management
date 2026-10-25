<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAddressTest extends TestCase
{
    use RefreshDatabase;

    private function space(string $name): array
    {
        config(['tenancy.enabled' => true]);
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'OwnerChoraleSecret2026']);

        return [Tenant::findOrFail($user->tenant_id), $user];
    }

    public function test_personal_address_renders_only_its_public_login_identity(): void
    {
        [$alpha] = $this->space('alpha');
        [$beta] = $this->space('beta');
        $this->get('/c/'.$alpha->slug)->assertOk()->assertSee('Chorale alpha')->assertDontSee('Chorale beta')->assertSee(route('tenant.login', ['slug' => $alpha->slug]), false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull(app(TenantContext::class)->id());
        $this->get('/c/'.$beta->slug)->assertOk()->assertSee('Chorale beta')->assertDontSee('Chorale alpha');
        $this->assertGuest();
        $this->get('/c/inconnue')->assertNotFound();
    }

    public function test_credentials_from_another_choir_cannot_authenticate_on_personal_address(): void
    {
        [$alpha] = $this->space('alpha');
        [, $betaUser] = $this->space('beta');
        $this->post('/c/'.$alpha->slug.'/connexion', ['login' => $betaUser->username, 'password' => 'OwnerChoraleSecret2026'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->post('/c/'.$alpha->slug.'/connexion', ['login' => $betaUser->email, 'password' => 'OwnerChoraleSecret2026'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_scoped_login_returns_to_own_dashboard_and_foreign_address_is_rejected(): void
    {
        [$alpha, $user] = $this->space('alpha');
        [$beta] = $this->space('beta');
        $this->post('/c/'.$alpha->slug.'/connexion', ['login' => $user->username, 'password' => 'OwnerChoraleSecret2026'])->assertRedirect('/c/'.$alpha->slug);
        $this->get('/c/'.$alpha->slug)->assertOk()->assertSee('Chorale alpha');
        $this->get('/c/'.$beta->slug)->assertForbidden();
        $this->assertAuthenticatedAs($user);
        $this->get('/parametres/chorale')->assertOk()->assertSee(route('tenant.entry', ['slug' => $alpha->slug]), false);
    }

    public function test_trial_expiration_on_personal_address_still_restricts_to_billing(): void
    {
        [$tenant, $user] = $this->space('expired');
        $tenant->forceFill(['trial_ends_at' => now()->subSecond()])->save();
        $this->actingAs($user)->get('/c/'.$tenant->slug)->assertRedirect('/abonnement');
    }
}
