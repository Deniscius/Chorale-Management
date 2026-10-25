<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Livewire\Commissions\Responsibilities;
use App\Livewire\Instruments\Index as Instruments;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Models\Document;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountWriter;
use App\Services\ChoraleBranding;
use App\Services\ChoraleIdentity;
use App\Services\CommissionAccess;
use App\Services\CommissionCatalog;
use App\Services\CommissionResponsibilities;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PersistentFilesAndCommissionAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function commission(array $data = []): ChoirCommission
    {
        return ChoirCommission::create(array_replace(['name' => 'Équipe des instruments', 'name_key' => 'équipe des instruments', 'mission' => 'Entretenir les instruments.', 'kind' => 'permanent', 'starts_on' => today(), 'module_key' => 'instruments'], $data));
    }

    public function test_logo_survives_local_storage_loss_cache_clear_and_new_service_instance(): void
    {
        config(['private-files.driver' => 'database']);
        Storage::fake('local');
        $raw = base64_decode(self::PNG);
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        app(ChoraleBranding::class)->save([], UploadedFile::fake()->createWithContent('logo.png', $raw));
        $path = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);
        $this->assertNotSame($raw, DB::table('private_files')->value('payload'));
        $this->assertDatabaseHas('private_files', ['path' => $path, 'size' => strlen($raw)]);
        Storage::fake('local');
        Cache::flush();
        $this->app->forgetInstance(PrivateFiles::class);
        app(ChoraleIdentity::class)->forget();
        $this->get(route('chorale.logo'))->assertOk()->assertStreamedContent($raw);
        $this->assertTrue(app(PrivateFiles::class)->exists($path));
    }

    public function test_stored_documents_keep_access_control_after_local_files_are_lost(): void
    {
        config(['private-files.driver' => 'database']);
        Storage::fake('local');
        $leader = User::factory()->role(UserRole::CommissionLiturgique)->create();
        CommissionTeam::where('commission', Commission::Liturgique->value)->update(['leader_id' => $leader->id]);
        app(PrivateFiles::class)->persist('documents/retained.pdf', '%PDF-1.4 retained', 'application/pdf');
        $doc = Document::factory()->create(['commission' => Commission::Liturgique, 'visibility' => 'private', 'path' => 'documents/retained.pdf', 'mime_type' => 'application/pdf', 'original_name' => 'Programme de messe.pdf']);
        Storage::fake('local');
        $this->actingAs($leader)->get(route('documents.download', $doc))->assertOk()->assertStreamedContent('%PDF-1.4 retained')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_quota_failure_preserves_the_existing_file(): void
    {
        config(['private-files.driver' => 'database']);
        $files = app(PrivateFiles::class);
        $files->persist('documents/one.txt', 'Original', 'text/plain');
        config(['private-files.tenant_quota' => DB::table('private_files')->sum('stored_bytes') + 1]);
        try {
            $files->persist('documents/two.txt', 'Rejected', 'text/plain');
            $this->fail('Quota bypass');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertSame(1, DB::table('private_files')->count());
        $this->assertTrue($files->exists('documents/one.txt'));
        $this->assertFalse($files->exists('documents/two.txt'));
    }

    public function test_member_reads_but_only_delegated_leader_manages_without_changing_role(): void
    {
        $member = Member::factory()->create();
        $reader = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $leaderMember = Member::factory()->create();
        $leader = User::factory()->role(UserRole::Choriste)->create(['member_id' => $leaderMember->id]);
        $entry = $this->commission();
        $entry->members()->attach($member->id);
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Responsibilities::class, ['commissionId' => $entry->id])->set('leader_id', (string) $leader->id)->call('save')->assertHasNoErrors();
        $this->assertSame(UserRole::Choriste, $leader->fresh()->role);
        $this->assertTrue($entry->members()->where('members.id', $leaderMember->id)->exists());
        $this->actingAs($reader)->get('/instruments')->assertOk();
        Livewire::actingAs($reader)->test(Instruments::class)->call('create')->assertForbidden();
        Livewire::actingAs($leader)->test(Instruments::class)->call('create')->assertSet('showForm', true);
        $other = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($other)->get('/instruments')->assertForbidden();
    }

    public function test_multiple_memberships_combine_read_access_and_revocation_is_immediate(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $instrument = $this->commission();
        $mail = $this->commission(['name' => 'Accueil', 'name_key' => 'accueil', 'module_key' => 'courriers']);
        $instrument->members()->attach($member->id);
        $mail->members()->attach($member->id);
        $this->assertTrue($user->canAccessModule('instruments'));
        $this->assertTrue($user->canAccessModule('courriers'));
        $this->assertFalse($user->canManageModule('courriers'));
        $instrument->members()->detach($member->id);
        app(CommissionAccess::class)->forget();
        $this->assertFalse($user->canAccessModule('instruments'));
        $this->assertTrue($user->canAccessModule('courriers'));
    }

    public function test_admin_delegates_private_archive_to_any_account_and_members_remain_excluded(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $member = Member::factory()->create();
        $reader = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $this->actingAs($admin);
        app(CommissionCatalog::class)->ensure();
        $entry = ChoirCommission::where('archive_commission', Commission::Secretariat->value)->firstOrFail();
        $entry->members()->attach($member->id);
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, $associate->id, 'comptabilite');
        $this->assertSame('courriers', $entry->fresh()->module_key);
        $doc = Document::factory()->create(['commission' => Commission::Secretariat, 'visibility' => 'private']);
        $this->assertTrue($doc->fresh()->canView($leader));
        $this->assertTrue($doc->fresh()->canView($associate));
        $this->assertFalse($doc->fresh()->canView($reader));
        $this->assertTrue(Document::readable($leader)->whereKey($doc->id)->exists());
        $this->assertFalse(Document::readable($reader)->whereKey($doc->id)->exists());
        $this->assertTrue($reader->canAccessModule('courriers'));
        $this->assertFalse($reader->canManageModule('courriers'));
        $this->assertTrue($leader->canManageModule('courriers'));
        $this->assertFalse($leader->canManageModule('comptabilite'));
    }

    public function test_expired_and_archived_commissions_stop_delegated_access(): void
    {
        $user = User::factory()->role(UserRole::Choriste)->create();
        $entry = $this->commission(['kind' => 'ad_hoc', 'leader_id' => $user->id, 'ends_on' => today()]);
        $this->assertTrue($user->canManageModule('instruments'));
        $this->travelTo(today()->addDay());
        app(CommissionAccess::class)->forget();
        $this->assertFalse($user->canAccessModule('instruments'));
        $this->travelBack();
        $entry->update(['archived_at' => now()]);
        app(CommissionAccess::class)->forget();
        $this->assertFalse($user->canAccessModule('instruments'));
    }

    public function test_only_admin_delegates_and_reference_setup_never_overwrites_delegation(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($admin);
        app(CommissionCatalog::class)->ensure();
        $entry = ChoirCommission::where('archive_commission', Commission::Instruments->value)->firstOrFail();
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'instruments');
        app(CommissionCatalog::class)->ensure();
        $this->assertSame($leader->id, (int) $entry->fresh()->leader_id);
        $this->assertSame(7, ChoirCommission::count());
        Livewire::actingAs(User::factory()->role(UserRole::Directeur)->create())->test(Responsibilities::class, ['commissionId' => $entry->id])->assertForbidden();
        Livewire::actingAs($leader)->test(Responsibilities::class, ['commissionId' => $entry->id])->assertForbidden();
    }

    public function test_files_and_delegations_cannot_cross_tenant_boundaries(): void
    {
        config(['tenancy.enabled' => true, 'private-files.driver' => 'database']);
        $a = Tenant::forceCreate(['name' => 'A', 'slug' => 'a', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $b = Tenant::forceCreate(['name' => 'B', 'slug' => 'b', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $ctx = app(TenantContext::class);
        $foreign = $ctx->run($b->id, fn () => User::factory()->create());
        $ctx->run($a->id, function () use ($a, $b, $foreign) {
            $admin = User::factory()->role(UserRole::Admin)->create();
            $this->actingAs($admin);
            $entry = $this->commission();
            Livewire::test(Responsibilities::class, ['commissionId' => $entry->id])->set('leader_id', (string) $foreign->id)->call('save')->assertHasErrors('leader_id');
            app(PrivateFiles::class)->persist('tenants/'.$a->id.'/logo.png', base64_decode(self::PNG), 'image/png');
            $this->expectException(HttpException::class);
            app(PrivateFiles::class)->exists('tenants/'.$b->id.'/logo.png');
        });
    }

    public function test_replacing_leader_revokes_write_and_private_archive_access(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $old = User::factory()->role(UserRole::Choriste)->create(['member_id' => Member::factory()->create()->id]);
        $new = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($admin);
        app(CommissionCatalog::class)->ensure();
        $entry = ChoirCommission::where('archive_commission', Commission::Secretariat->value)->firstOrFail();
        $writer = app(CommissionResponsibilities::class);
        $writer->assign($entry->id, $old->id, null, null);
        $doc = Document::factory()->create(['commission' => Commission::Secretariat, 'visibility' => 'private']);
        $this->assertTrue($old->canManageModule('courriers'));
        $this->assertTrue($doc->fresh()->canView($old));
        $writer->assign($entry->id, $new->id, null, null);
        $this->assertTrue($old->canAccessModule('courriers'));
        $this->assertFalse($old->canManageModule('courriers'));
        $this->assertFalse($doc->fresh()->canView($old));
        $this->assertFalse(Document::readable($old)->whereKey($doc->id)->exists());
        $this->assertTrue($doc->fresh()->canView($new));
    }

    public function test_expired_archive_commission_revokes_associate_private_access(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($admin);
        app(CommissionCatalog::class)->ensure();
        $entry = ChoirCommission::where('archive_commission', Commission::Liturgique->value)->firstOrFail();
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, $associate->id, null);
        $doc = Document::factory()->create(['commission' => Commission::Liturgique, 'visibility' => 'private']);
        $this->assertTrue($doc->fresh()->canView($associate));
        $entry->update(['starts_on' => today()->subDay(), 'ends_on' => today()->subDay()]);
        app(CommissionAccess::class)->forget();
        $this->assertFalse($doc->fresh()->canView($associate));
        $this->assertFalse(Document::readable($associate)->whereKey($doc->id)->exists());
    }

    public function test_account_with_responsibilities_cannot_be_deleted_until_reassigned(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $this->commission(['leader_id' => $leader->id]);
        $this->actingAs($admin);
        try {
            app(AccountWriter::class)->delete($leader);
            $this->fail('Delegated account deleted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('operation', $e->errors());
        }
        $this->assertNotNull($leader->fresh());
    }
}
