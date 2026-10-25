<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\Middleware\TenantJob;
use App\Jobs\SendReminder;
use App\Models\CommissionTeam;
use App\Models\Document;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Work;
use App\Services\DocumentArchive;
use App\Services\ReferenceSequence;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantSaasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true, 'tenancy.registration' => true]);
        Notification::fake();
        Http::preventStrayRequests();
    }

    private function space(string $name = 'alpha'): array
    {
        $user = app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable '.$name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'SecretChorale2026']);
        $tenant = Tenant::findOrFail($user->tenant_id);
        app(TenantContext::class)->run($tenant->id, fn () => $user->forceFill(['email_verified_at' => now()])->save());

        return [$tenant, $user];
    }

    public function test_signup_creates_an_isolated_space_and_one_fixed_trial(): void
    {
        $this->get('/creer-un-espace')->assertOk()->assertSee('72 heures');
        $this->post('/creer-un-espace', ['organization' => 'Nouvelle chorale', 'name' => 'Responsable', 'username' => 'nouvelle', 'email' => 'new@example.test', 'password' => 'ChoraleSecret2026', 'password_confirmation' => 'ChoraleSecret2026', 'terms' => '1', 'website' => ''])->assertRedirect(route('dashboard'));
        $user = auth()->user();
        $tenant = Tenant::findOrFail($user->tenant_id);
        $this->assertSame(72 * 3600, (int) $tenant->trial_started_at->diffInSeconds($tenant->trial_ends_at));
        $this->assertSame(1, DB::table('users')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(2, DB::table('commission_teams')->where('tenant_id', $tenant->id)->count());
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertNothingSent();
        $this->get('/dashboard')->assertOk();
        $this->get('/membres')->assertOk();
        $expiry = $tenant->trial_ends_at->toISOString();
        app(TenantContext::class)->run($tenant->id, fn () => User::factory()->create());
        $this->assertSame($expiry, $tenant->fresh()->trial_ends_at->toISOString());
    }

    public function test_signup_honeypot_rejects_bots_without_creating_a_space(): void
    {
        $before = Tenant::count();
        $users = DB::table('users')->count();
        $this->post('/creer-un-espace', ['organization' => 'Robot', 'name' => 'Robot', 'username' => 'robot', 'email' => 'robot@example.test', 'password' => 'ChoraleSecret2026', 'password_confirmation' => 'ChoraleSecret2026', 'terms' => '1', 'website' => 'https://spam.example'])->assertSessionHasErrors('organization');
        $this->assertGuest();
        $this->assertSame($before, Tenant::count());
        $this->assertSame($users, DB::table('users')->count());
        Notification::assertNothingSent();
    }

    public function test_signup_attempts_remain_rate_limited_without_email_verification(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/creer-un-espace', [])->assertSessionHasErrors();
        }
        $this->post('/creer-un-espace', [])->assertStatus(429);
    }

    public function test_unverified_trial_user_can_request_verification_later_for_payment(): void
    {
        [$tenant, $admin] = $this->space();
        app(TenantContext::class)->run($tenant->id, fn () => $admin->forceFill(['email_verified_at' => null])->save());
        $this->actingAs($admin)->get('/verify-email')->assertOk()->assertSee('Votre essai est accessible sans confirmation');
        Notification::assertNothingSent();
        $this->post('/email/verification-notification')->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($admin, VerifyEmail::class);
    }

    public function test_administrators_only_see_their_own_members_and_branding(): void
    {
        [$a, $adminA] = $this->space('alpha');
        [$b, $adminB] = $this->space('beta');
        app(TenantContext::class)->run($a->id, fn () => Member::factory()->create(['last_name' => 'PRIVE_ALPHA']));
        app(TenantContext::class)->run($b->id, fn () => Member::factory()->create(['last_name' => 'PRIVE_BETA']));
        $this->actingAs($adminA)->get('/membres')->assertOk()->assertSee('PRIVE_ALPHA')->assertDontSee('PRIVE_BETA')->assertSee('Chorale alpha')->assertDontSee('Chorale beta');
        $this->actingAs($adminB)->get('/membres')->assertOk()->assertSee('PRIVE_BETA')->assertDontSee('PRIVE_ALPHA')->assertSee('Chorale beta');
        $this->actingAs($adminA)->get('/membres')->assertOk()->assertSee('Chorale alpha')->assertDontSee('Chorale beta');
    }

    public function test_all_business_models_are_scoped_and_guest_queries_are_empty(): void
    {
        [$tenant, $user] = $this->space();
        app(TenantContext::class)->run($tenant->id, fn () => Member::factory()->create());
        $this->assertSame(0, Member::count());
        foreach (glob(app_path('Models/*.php')) as $file) {
            $name = basename($file, '.php');
            if ($name === 'Tenant') {
                continue;
            }
            $class = 'App\\Models\\'.$name;
            $this->assertStringContainsString('1 = 0', (new $class)->newQuery()->toSql(), $name.' must fail closed');
        }
    }

    public function test_unguarded_model_restoration_cannot_recover_another_tenant_row(): void
    {
        [$a] = $this->space('alpha');
        [$b] = $this->space('beta');
        $member = app(TenantContext::class)->run($b->id, fn () => Member::factory()->create());
        app(TenantContext::class)->run($a->id, function () use ($member) {
            $this->assertNull($member->newQueryForRestoration($member->id)->first());
            $this->assertFalse(Validator::make(['member_id' => $member->id], ['member_id' => 'exists:members,id'])->passes());
        });
    }

    public function test_database_constraints_refuse_cross_tenant_pivot_links(): void
    {
        [$a] = $this->space('alpha');
        [$b] = $this->space('beta');
        $rehearsal = app(TenantContext::class)->run($a->id, fn () => Rehearsal::factory()->create());
        $work = app(TenantContext::class)->run($b->id, fn () => Work::factory()->create());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('rehearsal_work')->insert(['tenant_id' => $a->id, 'rehearsal_id' => $rehearsal->id, 'work_id' => $work->id]));
    }

    public function test_trial_blocks_business_at_the_exact_deadline_and_keeps_logout_available(): void
    {
        [$tenant, $admin] = $this->space();
        $this->actingAs($admin);
        $this->travelTo($tenant->trial_ends_at->subSecond());
        $this->get('/membres')->assertOk();
        $this->travelTo($tenant->trial_ends_at);
        $this->get('/membres')->assertRedirect(route('billing.show'));
        $this->get('/profile')->assertRedirect(route('billing.show'));
        $this->get('/abonnement')->assertOk()->assertSee('Votre essai est terminé')->assertSee('Paiement bientôt disponible')->assertDontSee('href="https://chariow.com/fr"', false);
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_expired_open_livewire_page_and_direct_download_are_blocked(): void
    {
        [$tenant, $admin] = $this->space();
        $page = $this->actingAs($admin)->get('/dashboard')->assertOk();
        preg_match('/data-update-uri="([^"]+)"/', $page->getContent(), $match);
        $this->assertArrayHasKey(1, $match);
        $tenant->forceFill(['trial_ends_at' => now()->subSecond()])->save();
        $this->post($match[1], ['components' => []], ['X-Livewire' => 'true', 'Accept' => 'application/json'])->assertStatus(402)->assertJsonPath('redirect', route('billing.show'));
        $this->get('/documents/999/telecharger')->assertRedirect(route('billing.show'));
    }

    public function test_published_document_is_only_shared_inside_its_tenant(): void
    {
        Storage::fake('local');
        [$a, $adminA] = $this->space('alpha');
        [$b, $adminB] = $this->space('beta');
        $doc = app(TenantContext::class)->run($a->id, fn () => Document::factory()->create(['commission' => 'commission_liturgique', 'visibility' => 'published', 'path' => 'tenants/'.$a->id.'/public.pdf']));
        Storage::disk('local')->put($doc->path, '%PDF fictif');
        $this->actingAs($adminA)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($adminB)->get(route('documents.download', $doc))->assertNotFound();
    }

    public function test_login_and_password_authentication_work_without_a_tenant_cookie(): void
    {
        [$tenant, $user] = $this->space();
        $this->post('/login', ['login' => $user->username, 'password' => 'SecretChorale2026', 'remember' => true])->assertRedirect('/dashboard');
        $this->get('/membres')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    private function license(User $user, array $override = []): array
    {
        return array_replace(['id' => 'license_fictive', 'status' => 'active', 'is_active' => true, 'is_expired' => false, 'revoked_at' => null, 'expires_at' => now()->addDays(30)->toISOString(), 'product' => ['id' => 'prd_chorale', 'pricing' => ['price' => ['value' => 2000, 'currency' => 'XOF']]], 'customer' => ['email' => $user->email]], $override);
    }

    private function configureChariow(): void
    {
        config(['tenancy.chariow.api_key' => 'test-secret-no-real-payment', 'tenancy.chariow.product_id' => 'prd_chorale']);
    }

    public function test_license_is_verified_on_server_and_repeated_activation_does_not_extend_it(): void
    {
        [$tenant, $admin] = $this->space();
        $this->configureChariow();
        $expires = now()->addDays(30)->startOfSecond();
        Http::fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['expires_at' => $expires->toISOString()])])]);
        $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'SYNTHETIC-KEY'])->assertRedirect(route('dashboard'));
            $this->assertTrue($tenant->fresh()->paid_until->equalTo($expires));
        }
        $this->assertSame(1, DB::table('tenant_licenses')->count());
        $this->get('/dashboard')->assertOk();
    }

    public function test_wrong_product_and_invalid_license_cannot_unlock_the_space(): void
    {
        [$tenant, $admin] = $this->space();
        $this->configureChariow();
        foreach ([['product' => ['id' => 'prd_other']], ['is_active' => false], ['is_expired' => true], ['customer' => ['email' => 'other@example.test']], ['expires_at' => ''], ['expires_at' => []]] as $invalid) {
            $this->travel(61)->seconds();
            Http::fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, $invalid)])]);
            $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'SYNTHETIC-KEY'])->assertSessionHasErrors('license_key');
            $this->assertNull($tenant->fresh()->paid_until);
            $this->assertSame(0, DB::table('tenant_licenses')->count());
        }
    }

    public function test_unconfigured_payment_and_unverified_email_never_activate_access(): void
    {
        [$tenant, $admin] = $this->space();
        $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'KEY'])->assertSessionHasErrors('license_key');
        Http::assertNothingSent();
        app(TenantContext::class)->run($tenant->id, fn () => $admin->forceFill(['email_verified_at' => null])->save());
        $this->post('/abonnement/activer', ['license_key' => 'KEY'])->assertRedirect(route('verification.notice'));
        Http::assertNothingSent();
    }

    public function test_jobs_keep_their_tenant_and_stop_after_expiry(): void
    {
        [$tenant] = $this->space();
        $job = app(TenantContext::class)->run($tenant->id, fn () => new SendReminder(999, 'token', 'mail'));
        $seen = null;
        (new TenantJob)->handle($job, function () use (&$seen) {
            $seen = app(TenantContext::class)->id();
        });
        $this->assertSame($tenant->id, $seen);
        $this->assertNull(app(TenantContext::class)->id());
        $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $called = false;
        (new TenantJob)->handle($job, function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        config(['tenancy.enabled' => false]);
        $this->app['env'] = 'production';
        try {
            (new TenantJob)->handle($job, function () use (&$called) {
                $called = true;
            });
            $this->assertFalse($called);
        } finally {
            $this->app['env'] = 'testing';
            config(['tenancy.enabled' => true]);
        }
    }

    public function test_reference_sequences_and_settings_are_independent(): void
    {
        [$a] = $this->space('alpha');
        [$b] = $this->space('beta');
        foreach ([$a, $b] as $tenant) {
            app(TenantContext::class)->run($tenant->id, function () use ($tenant) {
                $first = app(ReferenceSequence::class)->next('transactions', 'REC', 2026);
                $this->assertSame('REC/2026/0001', $first);
                Setting::set('note', 'secret-'.$tenant->id);
                $this->assertSame('secret-'.$tenant->id, Setting::get('note'));
            });
        }
        app(TenantContext::class)->run($a->id, function () use ($a) {
            $this->assertSame('REC/2026/0002', app(ReferenceSequence::class)->next('transactions', 'REC', 2026));
            $this->assertSame('secret-'.$a->id, Setting::get('note'));
        });
    }

    public function test_foreign_financial_and_commission_references_are_rejected(): void
    {
        [$a, $adminA] = $this->space('alpha');
        [$b, $adminB] = $this->space('beta');
        $member = app(TenantContext::class)->run($b->id, fn () => Member::factory()->create());
        app(TenantContext::class)->run($a->id, function () use ($member, $adminB) {
            $this->assertFalse(Validator::make(['member_id' => $member->id, 'leader_id' => $adminB->id], ['member_id' => 'exists:members,id', 'leader_id' => 'exists:users,id'])->passes());
            try {
                CommissionTeam::where('commission', 'commission_liturgique')->firstOrFail()->update(['leader_id' => $adminB->id]);
                $this->fail('A leader from another space must be rejected');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('leader_id', $e->errors());
            }
        });
    }

    public function test_all_screens_render_for_a_new_space_without_demo_data(): void
    {
        [$tenant, $admin] = $this->space();
        $this->actingAs($admin);
        foreach (['/dashboard', '/membres', '/oeuvres', '/repetitions', '/celebrations', '/temps-liturgiques', '/rappels', '/instruments', '/instruments/mouvements', '/instruments/maintenance', '/instruments/ensembles', '/courriers', '/activites', '/transactions', '/budgets', '/cotisations', '/dons', '/documents', '/utilisateurs', '/administration/journal', '/parametres/chorale'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_one_license_cannot_be_reassigned_to_another_tenant(): void
    {
        [$a, $adminA] = $this->space('alpha');
        [$b, $adminB] = $this->space('beta');
        $this->configureChariow();
        Http::fake(['api.chariow.com/*' => Http::response(['data' => $this->license($adminA)])]);
        $this->actingAs($adminA)->post('/abonnement/activer', ['license_key' => 'SYNTHETIC-KEY'])->assertRedirect(route('dashboard'));
        Http::fake(['api.chariow.com/*' => Http::response(['data' => $this->license($adminB)])]);
        $this->actingAs($adminB)->post('/abonnement/activer', ['license_key' => 'SYNTHETIC-KEY'])->assertSessionHasErrors('license_key');
        $this->assertNull($b->fresh()->paid_until);
        $this->assertSame(1, DB::table('tenant_licenses')->count());
    }

    public function test_license_service_outage_does_not_grant_access_or_flash_the_secret(): void
    {
        [$tenant, $admin] = $this->space();
        $this->configureChariow();
        Http::fake(['api.chariow.com/*' => Http::response([], 503)]);
        $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'SECRET-LICENSE-EXAMPLE'])->assertSessionHasErrors('license_key');
        $this->assertNull(session()->get('_old_input.license_key'));
        $this->assertNull($tenant->fresh()->paid_until);
    }

    public function test_archive_uploads_use_a_private_tenant_directory(): void
    {
        Storage::fake('local');
        [$tenant] = $this->space();
        app(TenantContext::class)->run($tenant->id, function () use ($tenant) {
            $leader = User::factory()->role(UserRole::CommissionLiturgique)->create();
            CommissionTeam::where('commission', 'commission_liturgique')->firstOrFail()->update(['leader_id' => $leader->id]);
            $this->actingAs($leader);
            $doc = app(DocumentArchive::class)->save(null, ['title' => 'Partition isolée', 'commission' => 'commission_liturgique', 'visibility' => 'private'], UploadedFile::fake()->create('partition.pdf', 10, 'application/pdf'));
            $this->assertStringStartsWith('tenants/'.$tenant->id.'/documents/', $doc->path);
            Storage::disk('local')->assertExists($doc->path);
        });
    }

    public function test_disabling_tenancy_outside_tests_fails_closed(): void
    {
        $this->space();
        config(['tenancy.enabled' => false]);
        $this->app['env'] = 'production';
        try {
            $this->assertNull(app(TenantContext::class)->id());
            $this->assertSame(0, User::count());
            $this->get('/login')->assertStatus(503);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_lifetime_license_cannot_bypass_the_monthly_subscription(): void
    {
        [$tenant, $admin] = $this->space();
        $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->configureChariow();
        Http::fake(['api.chariow.com/*' => Http::response(['data' => $this->license($admin, ['expires_at' => null])])]);
        $this->actingAs($admin)->post('/abonnement/activer', ['license_key' => 'LIFETIME-SYNTHETIC'])->assertSessionHasErrors('license_key');
        $this->assertFalse($tenant->fresh()->paid_perpetual);
        $this->assertNull($tenant->fresh()->paid_until);
        $this->assertFalse($tenant->fresh()->hasAccess());
        $this->assertDatabaseCount('tenant_licenses', 0);
    }

    public function test_expired_billing_does_not_load_a_private_logo_or_exempt_its_route(): void
    {
        Storage::fake('local');
        [$tenant, $admin] = $this->space();
        $path = 'tenants/'.$tenant->id.'/chorale/logo.png';
        Storage::disk('local')->put($path, 'image fictive');
        app(TenantContext::class)->run($tenant->id, fn () => Setting::set('chorale.logo_path', $path));
        $tenant->forceFill(['trial_ends_at' => now()->subSecond()])->save();
        $this->actingAs($admin)->get('/abonnement')->assertOk()->assertDontSee(route('chorale.logo'), false);
        $this->get(route('chorale.logo'))->assertRedirect(route('billing.show'));
    }
}
