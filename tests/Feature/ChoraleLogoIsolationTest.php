<?php

namespace Tests\Feature;

use App\Livewire\Settings\Chorale;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\ChoraleBranding;
use App\Services\ChoraleIdentity;
use App\Services\PrivateFiles;
use App\Services\SettingsStore;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChoraleLogoIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true, 'cache.default' => 'database', 'private-files.driver' => 'database']);
        Storage::fake('local');
    }

    private function space(string $name, bool $withLogo = true): array
    {
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'TestOnlyChoraleSecret2026!']);
        $tenant = Tenant::findOrFail($user->tenant_id);
        $raw = base64_decode(self::PNG).$name;
        if ($withLogo) {
            app(TenantContext::class)->run($tenant->id, fn () => app(ChoraleBranding::class)->save([], UploadedFile::fake()->createWithContent('logo.png', $raw)));
        }

        return [$tenant, $user, $raw];
    }

    private function favicon($response): string
    {
        $this->assertSame(1, preg_match('/<link rel="icon" href="([^"]+)"/', $response->getContent(), $matches));

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    public function test_logos_are_private_and_urls_cannot_be_reused_by_another_choir(): void
    {
        [$alpha, $alphaUser, $alphaRaw] = $this->space('alpha');
        [$beta, $betaUser, $betaRaw] = $this->space('beta');
        $alphaUrl = $this->favicon($this->actingAs($alphaUser)->get('/dashboard')->assertOk()->assertSee('Logo Chorale alpha')->assertDontSee('Logo Chorale beta'));
        $this->assertStringContainsString('tenant='.$alpha->id, $alphaUrl);
        $this->get($alphaUrl)->assertOk()->assertStreamedContent($alphaRaw)->assertHeader('Cache-Control', 'no-store, private');

        $betaUrl = $this->favicon($this->actingAs($betaUser)->get('/dashboard')->assertOk()->assertSee('Logo Chorale beta')->assertDontSee('Logo Chorale alpha'));
        $this->assertNotSame($alphaUrl, $betaUrl);
        $this->get($betaUrl)->assertOk()->assertStreamedContent($betaRaw);
        $this->get($alphaUrl)->assertNotFound();
        $this->get(route('tenant.logo', ['slug' => $alpha->slug]))->assertForbidden();
        $this->get(route('tenant.logo', ['slug' => $beta->slug]))->assertOk()->assertStreamedContent($betaRaw);

        $this->post('/logout')->assertRedirect('/');
        $this->get($alphaUrl)->assertRedirect(route('login'));
        $this->get($betaUrl)->assertRedirect(route('login'));
        $this->get(route('tenant.logo', ['slug' => $alpha->slug]))->assertRedirect(route('login'));
    }

    public function test_login_pages_and_a_choir_without_a_logo_never_keep_another_choirs_image(): void
    {
        [$alpha, $alphaUser] = $this->space('alpha');
        [, $betaUser] = $this->space('beta', false);
        $alphaUrl = $this->favicon($this->actingAs($alphaUser)->get('/dashboard')->assertOk());
        $this->post('/logout')->assertRedirect('/');
        foreach (['/login', '/forgot-password', '/c/'.$alpha->slug, '/plateforme/connexion'] as $page) {
            $response = $this->get($page)->assertOk()
                ->assertDontSee('Logo Chorale alpha')->assertDontSee($alphaUrl, false)
                ->assertDontSee('/chorale/logo', false)->assertDontSee('/c/'.$alpha->slug.'/logo', false);
            $this->assertSame(asset('brand-icon.svg'), $this->favicon($response));
        }
        $response = $this->actingAs($betaUser)->get('/dashboard')->assertOk()->assertDontSee('Logo Chorale alpha');
        $this->assertSame(asset('brand-icon.svg'), $this->favicon($response));
    }

    public function test_branding_memory_is_scoped_even_when_service_references_are_retained(): void
    {
        [$alpha] = $this->space('alpha');
        [$beta] = $this->space('beta');
        // A retained service must use the active tenant rather than its previous memo.
        $identity = new ChoraleIdentity;
        $settings = new SettingsStore;
        app(TenantContext::class)->run($alpha->id, function () use ($identity, $settings) {
            $this->assertSame('Chorale alpha', $identity->name());
            $this->assertSame('Chorale alpha', $settings->get(ChoraleIdentity::KEY_NAME));
            $this->assertTrue($identity->hasLogo());
        });
        app(TenantContext::class)->run($beta->id, function () use ($identity, $settings) {
            $this->assertSame('Chorale beta', $identity->name());
            $this->assertSame('Chorale beta', $settings->get(ChoraleIdentity::KEY_NAME));
            $this->assertTrue($identity->hasLogo());
        });
        $this->assertFalse($identity->hasLogo());
        $this->assertSame([], $settings->all());
    }

    public function test_replacing_or_removing_one_logo_does_not_change_another_choir(): void
    {
        [$alpha, $alphaUser] = $this->space('alpha');
        [$beta, $betaUser, $betaRaw] = $this->space('beta');
        $betaUrl = $this->favicon($this->actingAs($betaUser)->get('/dashboard')->assertOk());
        app(TenantContext::class)->set($alpha->id);
        $this->actingAs($alphaUser);
        Livewire::test(Chorale::class)
            ->set('logo', UploadedFile::fake()->createWithContent('replacement.png', base64_decode(self::PNG)))
            ->call('save')->assertHasNoErrors();
        Livewire::test(Chorale::class)->call('removeLogo')->assertHasNoErrors();
        $this->assertNull(Setting::get(ChoraleIdentity::KEY_LOGO_PATH));
        $response = $this->actingAs($betaUser)->get('/dashboard')->assertOk();
        $this->assertSame($betaUrl, $this->favicon($response));
        $this->get($betaUrl)->assertOk()->assertStreamedContent($betaRaw);
        app(TenantContext::class)->run($beta->id, fn () => $this->assertTrue(app(PrivateFiles::class)->exists(Setting::get(ChoraleIdentity::KEY_LOGO_PATH))));
    }
}
