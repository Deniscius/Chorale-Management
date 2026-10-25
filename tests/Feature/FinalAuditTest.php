<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Rehearsals\Index;
use App\Livewire\Users\Form;
use App\Models\Document;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Platform\Admin;
use App\Models\Rehearsal;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountWriter;
use App\Services\Ai\AiClient;
use App\Services\ChoraleBranding;
use App\Services\ChoraleIdentity;
use App\Services\MemberOnboarding;
use App\Services\Platform\Totp;
use App\Services\SettingsStore;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinalAuditTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
    }

    private function space(string $name = 'alpha'): array
    {
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'ChoraleSecret2026']);

        return [Tenant::findOrFail($user->tenant_id), $user];
    }

    private function within(Tenant $tenant, \Closure $callback): mixed
    {
        return app(TenantContext::class)->run($tenant->id, $callback);
    }

    public function test_administration_validates_global_username_on_the_correct_field(): void
    {
        [$a, $admin] = $this->space('alpha');
        [$b] = $this->space('beta');
        $this->within($a, function () use ($admin) {
            Livewire::actingAs($admin)->test(Form::class)->set('name', 'Nouveau compte')->set('username', 'beta')->set('email', 'new@example.test')->set('password', 'Secret2026')->set('password_confirmation', 'Secret2026')->call('save')->assertHasErrors(['username' => 'unique']);
        });
        $this->assertSame(1, DB::table('users')->where('tenant_id', $a->id)->count());
    }

    public function test_member_access_validates_global_username_and_normalizes_email(): void
    {
        [$a, $admin] = $this->space('alpha');
        $this->space('beta');
        $this->within($a, function () use ($admin) {
            $member = Member::factory()->create();
            $url = app(MemberOnboarding::class)->issue($member);
            $data = ['username' => 'beta', 'email' => ' NEW@EXAMPLE.TEST ', 'password' => 'SecretPersonnel2026', 'password_confirmation' => 'SecretPersonnel2026'];
            $this->post($url, $data)->assertSessionHasErrors('username');
            $this->post($url, array_merge($data, ['username' => 'new.account']))->assertRedirect(route('dashboard'));
            auth()->logout();
            app(TenantContext::class)->set($admin->tenant_id);
            $this->assertSame('new@example.test', $member->fresh()->user->email);
        });
    }

    public function test_username_suggestion_handles_a_collision_in_another_space_and_long_names(): void
    {
        [$a, $admin] = $this->space('alpha');
        [$b] = $this->space('beta');
        $this->within($b, fn () => User::factory()->create(['username' => 'jean']));
        $this->within($a, function () {
            $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Kouassi']);
            $suggestion = app(MemberOnboarding::class)->suggestUsername($member);
            $this->assertSame('jean.2', $suggestion);
            $long = Member::factory()->create(['first_name' => str_repeat('Jean', 15), 'last_name' => 'Kouassi']);
            $this->assertLessThanOrEqual(50, strlen(app(MemberOnboarding::class)->suggestUsername($long)));
        });
    }

    public function test_email_changes_from_user_administration_reset_verification(): void
    {
        [$tenant, $admin] = $this->space();
        $this->within($tenant, function () use ($admin) {
            $admin->forceFill(['email_verified_at' => now()])->save();
            Livewire::actingAs($admin)->test(Form::class, ['user' => $admin])->set('email', ' CHANGED@EXAMPLE.TEST ')->call('save')->assertHasNoErrors();
            $this->assertNull($admin->fresh()->email_verified_at);
            $this->assertSame('changed@example.test', $admin->fresh()->email);
        });
    }

    public function test_unchanged_email_keeps_its_verification(): void
    {
        [$tenant, $admin] = $this->space();
        $this->within($tenant, function () use ($admin) {
            $admin->forceFill(['email_verified_at' => now()])->save();
            app(AccountWriter::class)->save($admin, ['name' => 'Nom modifié']);
            $this->assertNotNull($admin->fresh()->email_verified_at);
        });
    }

    public function test_concurrent_account_uniqueness_conflict_reports_username_not_member(): void
    {
        [$a] = $this->space('alpha');
        $this->space('beta');
        $this->within($a, function () {
            try {
                app(AccountWriter::class)->save(null, ['name' => 'Nouveau', 'username' => 'beta', 'email' => 'other@example.test', 'role' => 'choriste', 'password' => 'Secret2026']);
                $this->fail('Duplicate username accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('username', $e->errors());
                $this->assertArrayNotHasKey('member_id', $e->errors());
            }
        });
    }

    public function test_login_whitespace_variants_cannot_bypass_attempt_limits(): void
    {
        [, $admin] = $this->space();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => str_repeat(' ', $i).$admin->username, 'password' => 'incorrect'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => '  '.$admin->username.'  ', 'password' => 'ChoraleSecret2026'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_password_change_revokes_an_existing_business_session(): void
    {
        [$tenant, $admin] = $this->space();
        $this->post('/login', ['login' => $admin->username, 'password' => 'ChoraleSecret2026'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->within($tenant, fn () => $admin->fresh()->forceFill(['password' => 'ChangedSecret2026'])->save());
        Auth::guard('web')->forgetUser();
        $this->get('/membres')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_profile_password_change_keeps_its_current_session_valid(): void
    {
        [, $admin] = $this->space();
        $this->post('/login', ['login' => $admin->username, 'password' => 'ChoraleSecret2026'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->put('/password', ['current_password' => 'ChoraleSecret2026', 'password' => 'ChangedSecret2026', 'password_confirmation' => 'ChangedSecret2026'])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');
        Auth::guard('web')->forgetUser();
        $this->get('/dashboard')->assertOk();
    }

    public function test_rejected_business_session_does_not_revoke_the_owner_guard(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'OwnerPassword2026', 'totp_secret' => $secret]);
        $this->post('/plateforme/connexion', ['email' => 'owner@example.test', 'password' => 'OwnerPassword2026', 'code' => app(Totp::class)->code($secret, intdiv(now()->timestamp, 30))])->assertRedirect('/plateforme');
        [$tenant, $admin] = $this->space();
        $this->post('/login', ['login' => $admin->username, 'password' => 'ChoraleSecret2026'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->within($tenant, fn () => $admin->fresh()->forceFill(['password' => 'ChangedSecret2026'])->save());
        Auth::guard('web')->forgetUser();
        $this->get('/membres')->assertRedirect(route('login'));
        $this->get('/plateforme')->assertOk();
    }

    public function test_published_document_policy_itself_rejects_another_tenant_user(): void
    {
        [$a] = $this->space('alpha');
        [$b, $other] = $this->space('beta');
        $doc = $this->within($a, fn () => Document::factory()->create(['commission' => 'commission_liturgique', 'visibility' => 'published']));
        $this->within($b, function () use ($doc, $other) {
            $this->assertFalse($doc->canView($other));
            $this->assertFalse($doc->canManage($other));
        });
    }

    public function test_dashboard_cache_is_separated_by_permissions_and_skips_forbidden_queries(): void
    {
        [$tenant, $admin] = $this->space();
        $singer = $this->within($tenant, function () {
            Transaction::factory()->create(['type' => 'recette', 'amount' => '1000.00', 'status' => 'validee']);

            return User::factory()->role(UserRole::Choriste)->create();
        });
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertViewHas('kpis', fn ($kpis) => (float) $kpis['solde'] === 1000.0);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($singer)->get('/dashboard')->assertOk()->assertViewHas('kpis', fn ($kpis) => (float) $kpis['solde'] === 0.0)->assertDontSee('Solde trésorerie');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], array_values(array_filter($queries, fn ($q) => preg_match('/from\s+["`]?transactions/i', $q['query']))));
    }

    public function test_dashboard_uses_one_financial_aggregate_and_excludes_cancelled_events(): void
    {
        [$tenant, $admin] = $this->space();
        $this->within($tenant, function () {
            Transaction::factory()->create(['type' => 'recette', 'amount' => '1000.00', 'status' => 'validee']);
            Transaction::factory()->create(['type' => 'depense', 'amount' => '200.00', 'status' => 'cloturee']);
            Transaction::factory()->create(['type' => 'recette', 'amount' => '500.00', 'status' => 'brouillon']);
            Transaction::factory()->create(['type' => 'transfert', 'amount' => '5000.00', 'status' => 'validee']);
            foreach (['annulee', 'terminee', 'planifiee'] as $status) {
                Rehearsal::factory()->create(['date' => today()->addDay(), 'status' => $status]);
                Performance::factory()->create(['scheduled_at' => now()->addDay(), 'status' => $status]);
            }
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertViewHas('kpis', fn ($kpis) => (float) $kpis['solde'] === 800.0 && $kpis['repetitions'] === 1 && $kpis['concerts'] === 1)->assertViewHas('prochainesCelebrations', fn ($items) => $items->count() === 1);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn ($q) => preg_match('/from\s+["`]?transactions/i', $q['query'])));
    }

    public function test_access_metadata_uses_the_later_entitlement_and_business_pages_are_not_cached(): void
    {
        [$tenant, $admin] = $this->space();
        $tenant->forceFill(['paid_until' => now()->addMonth()])->save();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('name="tenant-access-expires" content="'.$tenant->paid_until->toIso8601String().'"', false);
        $tenant->forceFill(['paid_perpetual' => true])->save();
        $this->get('/dashboard')->assertOk()->assertDontSee('name="tenant-access-expires"', false);
    }

    public function test_failed_logo_storage_preserves_previous_logo_and_identity(): void
    {
        Storage::fake('local');
        [$tenant] = $this->space();
        $this->within($tenant, function () {
            Storage::disk('local')->put(app(TenantContext::class)->path('old.png'), base64_decode(self::PNG));
            Setting::setMany([ChoraleIdentity::KEY_NAME => 'Avant', ChoraleIdentity::KEY_LOGO_PATH => app(TenantContext::class)->path('old.png')]);
            $file = \Mockery::mock(UploadedFile::class);
            $file->shouldReceive('store')->once()->andReturn(false);
            try {
                app(ChoraleBranding::class)->save([ChoraleIdentity::KEY_NAME => 'Après'], $file);
                $this->fail('Storage failure ignored');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('logo', $e->errors());
            }
            $this->assertSame('Avant', Setting::get(ChoraleIdentity::KEY_NAME));
            $this->assertSame(app(TenantContext::class)->path('old.png'), Setting::get(ChoraleIdentity::KEY_LOGO_PATH));
            Storage::disk('local')->assertExists(app(TenantContext::class)->path('old.png'));
        });
    }

    public function test_failed_branding_transaction_cleans_the_new_file_and_preserves_the_old_one(): void
    {
        Storage::fake('local');
        [$tenant] = $this->space();
        $this->within($tenant, function () use ($tenant) {
            Storage::disk('local')->put(app(TenantContext::class)->path('old.png'), base64_decode(self::PNG));
            Setting::setMany([ChoraleIdentity::KEY_NAME => 'Avant', ChoraleIdentity::KEY_LOGO_PATH => app(TenantContext::class)->path('old.png')]);
            $armed = true;
            DB::listen(function ($query) use (&$armed) {
                if ($armed && str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'settings')) {
                    $armed = false;
                    throw new \RuntimeException('Synthetic database failure');
                }
            });
            try {
                app(ChoraleBranding::class)->save([ChoraleIdentity::KEY_NAME => 'Après'], UploadedFile::fake()->createWithContent('new.png', base64_decode(self::PNG)));
                $this->fail('Database failure ignored');
            } catch (\RuntimeException $e) {
                $this->assertSame('Synthetic database failure', $e->getMessage());
            }
            $this->assertSame('Avant', Setting::get(ChoraleIdentity::KEY_NAME));
            Storage::disk('local')->assertExists(app(TenantContext::class)->path('old.png'));
            $this->assertSame([], Storage::disk('local')->allFiles('tenants/'.$tenant->id.'/chorale'));
        });
    }

    public function test_settings_flush_the_persistent_cache_again_after_commit_in_their_own_context(): void
    {
        [$tenant] = $this->space();
        DB::transaction(function () use ($tenant) {
            $this->within($tenant, fn () => Setting::set(ChoraleIdentity::KEY_NAME, 'Après commit'));
            Cache::put('tenant:'.$tenant->id.':'.Setting::CACHE_KEY, [ChoraleIdentity::KEY_NAME => 'Valeur périmée'], 3600);
        });
        $this->within($tenant, fn () => $this->assertSame('Après commit', Setting::get(ChoraleIdentity::KEY_NAME)));
    }

    public function test_demo_seeders_refuse_production_before_creating_accounts(): void
    {
        $original = app()->environment();
        $before = DB::table('users')->count();
        app()->instance('env', 'production');
        try {
            foreach ([DatabaseSeeder::class, AdminUserSeeder::class] as $class) {
                try {
                    (new $class)->run();
                    $this->fail('Production seeding accepted');
                } catch (\LogicException $e) {
                    $this->assertStringContainsString('production', $e->getMessage());
                }
            }
        } finally {
            app()->instance('env', $original);
        }
        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_production_check_is_read_only_and_never_prints_secret_values(): void
    {
        $this->space();
        config(['tenancy.chariow.api_key' => 'SECRET_NOT_FOR_OUTPUT', 'tenancy.chariow.product_id' => 'product', 'tenancy.chariow.payment_url' => 'https://example.com/pay']);
        $before = DB::table('users')->count();
        $this->artisan('chorale:check-production')->expectsOutputToContain('Environnement production')->expectsOutputToContain('À corriger')->doesntExpectOutputToContain('SECRET_NOT_FOR_OUTPUT')->assertFailed();
        $this->assertSame($before, DB::table('users')->count());
        $this->assertTrue(config('tenancy.enabled'));
    }

    public function test_unknown_login_never_resolves_a_zero_identifier_account(): void
    {
        [$tenant] = $this->space();
        $this->within($tenant, fn () => User::factory()->create(['id' => 0, 'username' => 'zero', 'email' => 'zero@example.test', 'password' => 'KnownSecret2026']));
        $this->post('/login', ['login' => 'unknown', 'password' => 'KnownSecret2026'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_password_revocation_is_enforced_on_an_already_open_livewire_component(): void
    {
        [$tenant, $admin] = $this->space();
        $this->post('/login', ['login' => $admin->username, 'password' => 'ChoraleSecret2026'])->assertRedirect(route('dashboard'));
        $page = $this->get('/membres')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $page, $snapshot);
        preg_match('/data-update-uri="([^"]+)"/', $page, $uri);
        $this->assertArrayHasKey(1, $snapshot);
        $this->assertArrayHasKey(1, $uri);
        $this->within($tenant, fn () => $admin->fresh()->forceFill(['password' => 'ChangedSecret2026'])->save());
        Auth::guard('web')->forgetUser();
        $this->postJson($uri[1], ['_token' => session()->token(), 'components' => [['snapshot' => html_entity_decode($snapshot[1], ENT_QUOTES | ENT_HTML5), 'updates' => [], 'calls' => [['path' => '', 'method' => 'resetFilters', 'params' => []]]]]], ['X-Livewire' => 'true'])->assertUnauthorized();
    }

    public function test_malformed_provider_identifiers_and_invalid_calendar_dates_never_grant_a_license(): void
    {
        [$tenant, $admin] = $this->space();
        $this->within($tenant, fn () => $admin->forceFill(['email_verified_at' => now()])->save());
        config(['tenancy.chariow.api_key' => 'fake', 'tenancy.chariow.product_id' => 'product']);
        foreach ([['id' => ''], ['id' => str_repeat('x', 256)], ['expires_at' => '2099-02-30T00:00:00Z']] as $invalid) {
            Http::fake(['api.chariow.com/*' => Http::response(['data' => array_replace(['id' => 'license', 'is_active' => true, 'is_expired' => false, 'status' => 'active', 'revoked_at' => null, 'product' => ['id' => 'product', 'pricing' => ['price' => ['value' => 2000, 'currency' => 'XOF']]], 'customer' => ['email' => $admin->email], 'expires_at' => '2099-12-31T00:00:00Z'], $invalid)], 200)]);
            $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'FAKE'])->assertSessionHasErrors('license_key');
        }
        $this->assertNull($tenant->fresh()->paid_until);
        $this->assertDatabaseCount('tenant_licenses', 0);
    }

    public function test_ai_failure_keeps_the_draft_and_does_not_expose_exception_secrets(): void
    {
        [$tenant, $admin] = $this->space();
        config(['ai.enabled' => true, 'ai.roles' => ['admin']]);
        $this->app->instance(AiClient::class, new class implements AiClient
        {
            public function complete(string $system, string $prompt): string
            {
                throw new \RuntimeException('SECRET_NOT_FOR_OUTPUT');
            }
        });
        $this->within($tenant, function () use ($admin) {
            $rehearsal = Rehearsal::factory()->create();
            Log::spy();
            Livewire::actingAs($admin)->test(Index::class)->call('openReport', $rehearsal->id)->set('reportDraft', 'Brouillon à conserver')->call('generateReport')->assertSet('reportDraft', 'Brouillon à conserver')->assertDispatched('toast', fn ($event, $params) => ! str_contains($params['message'], 'SECRET_NOT_FOR_OUTPUT'));
            Log::shouldHaveReceived('warning')->with('Génération du compte rendu indisponible.', \Mockery::on(fn ($data) => ! str_contains(json_encode($data), 'SECRET_NOT_FOR_OUTPUT')))->once();
        });
    }

    public function test_login_attempts_do_not_consume_the_registration_hourly_quota(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['login' => 'unknown', 'password' => 'incorrect'])->assertSessionHasErrors('login');
        }
        $this->post('/creer-un-espace', ['organization' => 'Nouvel espace', 'name' => 'Responsable', 'username' => 'new.space', 'email' => 'new.space@example.test', 'password' => 'ChoraleSecret2026', 'password_confirmation' => 'ChoraleSecret2026', 'terms' => '1'])->assertRedirect(route('dashboard'));
    }

    public function test_cache_failure_after_commit_never_deletes_the_new_committed_logo(): void
    {
        Storage::fake('local');
        [$tenant] = $this->space();
        $this->within($tenant, function () {
            Storage::disk('local')->put(app(TenantContext::class)->path('old.png'), base64_decode(self::PNG));
            Setting::set(ChoraleIdentity::KEY_LOGO_PATH, app(TenantContext::class)->path('old.png'));
            Log::shouldReceive('warning')->once()->andThrow(new \RuntimeException('Synthetic log destination failure'));
            $this->app->instance(SettingsStore::class, new class extends SettingsStore
            {
                private int $calls = 0;

                public function flush(): void
                {
                    if (++$this->calls === 2) {
                        throw new \RuntimeException('Synthetic cache incident after commit');
                    }
                    parent::flush();
                }
            });
            app(ChoraleBranding::class)->save([], UploadedFile::fake()->createWithContent('new.png', base64_decode(self::PNG)));
            $path = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);
            $this->assertNotSame(app(TenantContext::class)->path('old.png'), $path);
            Storage::disk('local')->assertExists($path);
            Storage::disk('local')->assertMissing(app(TenantContext::class)->path('old.png'));
        });
    }
}
