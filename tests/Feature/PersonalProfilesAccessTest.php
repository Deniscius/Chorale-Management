<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Livewire\Users\CommissionAssignments;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionAccess;
use App\Services\CommissionResponsibilities;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalProfilesAccessTest extends TestCase
{
    use RefreshDatabase;

    private function commission(string $name, string $module): ChoirCommission
    {
        return ChoirCommission::create(['name' => $name, 'name_key' => strtolower($name), 'kind' => 'permanent', 'mission' => 'Mission de validation', 'starts_on' => today(), 'module_key' => $module]);
    }

    public function test_owner_completes_a_real_member_profile_without_choosing_privileges_or_another_person(): void
    {
        $user = User::factory()->create();
        $other = Member::factory()->create();
        $this->actingAs($user)->patch('/profile', [
            'first_name' => 'Anne', 'last_name' => 'Koffi', 'email' => $user->email,
            'birth_date' => '1995-04-12', 'phone' => '+22890000000', 'profession' => 'Enseignante',
            'role' => 'admin', 'member_id' => $other->id, 'tenant_id' => 999, 'voice' => 'bass',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh();
        $this->assertNotSame($other->id, $user->member_id);
        $this->assertSame('Anne Koffi', $user->name);
        $this->assertSame(UserRole::Choriste, $user->role);
        $this->assertSame('1995-04-12', $user->member->birth_date->toDateString());
        $this->assertSame('+22890000000', $user->member->phone);
        $this->assertNull($user->member->voice);
        $this->assertFalse($user->member->notify_sms);
        $this->assertFalse($user->member->notify_email);
        $this->assertSame(2, Member::count());
    }

    public function test_contact_changes_require_identity_when_completing_an_old_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'birth_date' => '1990-01-01'])
            ->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertNull($user->fresh()->member_id);
        $this->assertSame(0, Member::count());
    }

    public function test_future_birth_date_rolls_back_all_personal_changes(): void
    {
        $member = Member::factory()->create(['first_name' => 'Anne']);
        $user = User::factory()->create(['member_id' => $member->id]);
        $this->actingAs($user)->patch('/profile', ['first_name' => 'Changed', 'last_name' => 'Koffi', 'email' => $user->email, 'birth_date' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('birth_date');
        $this->assertSame('Anne', $member->fresh()->first_name);
    }

    public function test_existing_member_at_same_email_is_not_silently_claimed(): void
    {
        $member = Member::factory()->create(['email' => 'same@example.test']);
        $user = User::factory()->create(['email' => $member->email]);
        $this->actingAs($user)->patch('/profile', ['first_name' => 'Claim', 'last_name' => 'Denied', 'email' => $user->email])
            ->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->member_id);
        $this->assertNotSame('Claim', $member->fresh()->first_name);
    }

    public function test_administrator_assigns_multiple_commissions_and_demotion_removes_only_the_delegation(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $instruments = $this->commission('Matériel', 'instruments');
        $finance = $this->commission('Trésorerie', 'comptabilite');
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('members', [$finance->id])->set('leaders', [$instruments->id])->call('save')->assertHasNoErrors();
        $this->assertSame(UserRole::Choriste, $user->fresh()->role);
        $this->assertTrue($instruments->members()->where('members.id', $member->id)->exists());
        app(CommissionAccess::class)->forget();
        $this->assertTrue($user->canManageModule('instruments'));
        $this->assertTrue($user->canAccessModule('comptabilite'));
        $this->assertFalse($user->canManageModule('comptabilite'));
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('leaders', [])->call('save')->assertHasNoErrors();
        app(CommissionAccess::class)->forget();
        $this->assertFalse($user->canManageModule('instruments'));
        $this->assertTrue($instruments->members()->where('members.id', $member->id)->exists());
    }

    public function test_a_chorister_cannot_assign_their_own_commission_rights(): void
    {
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $this->actingAs($user)->get(route('users.commissions', $user))->assertForbidden();
        Livewire::actingAs($user)->test(CommissionAssignments::class, ['user' => $user])->assertForbidden();
    }

    public function test_existing_other_leader_is_not_replaced_and_the_whole_change_rolls_back(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $other = User::factory()->create();
        $entry = $this->commission('Matériel', 'instruments');
        $entry->update(['leader_id' => $other->id]);
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('members', [$entry->id])->set('leaders', [$entry->id])->call('save')->assertHasErrors('assignments');
        $this->assertSame($other->id, $entry->fresh()->leader_id);
        $this->assertFalse($entry->members()->where('members.id', $user->member_id)->exists());
    }

    public function test_cross_tenant_user_and_commission_cannot_be_selected(): void
    {
        config(['tenancy.enabled' => true]);
        $a = app(TenantRegistration::class)->create(['organization' => 'A', 'name' => 'Admin A', 'username' => 'admin-a', 'email' => 'a@example.test', 'password' => 'TestOnlyPassword2026!']);
        $b = app(TenantRegistration::class)->create(['organization' => 'B', 'name' => 'Admin B', 'username' => 'admin-b', 'email' => 'b@example.test', 'password' => 'TestOnlyPassword2026!']);
        $tenant = Tenant::findOrFail($a->tenant_id);
        $tenant->forceFill(['paid_perpetual' => true])->save();
        $otherId = app(TenantContext::class)->run($b->tenant_id, fn () => $this->commission('Étrangère', 'instruments')->id);
        $member = app(TenantContext::class)->run($a->tenant_id, fn () => Member::factory()->create());
        $target = app(TenantContext::class)->run($a->tenant_id, fn () => User::factory()->create(['member_id' => $member->id]));
        $this->actingAs($a)->get(route('users.commissions', $b))->assertNotFound();
        app(TenantContext::class)->run($a->tenant_id, function () use ($a, $target, $otherId) {
            Livewire::actingAs($a)->test(CommissionAssignments::class, ['user' => $target])
                ->set('leaders', [$otherId])->call('save')->assertHasErrors('assignments');
        });
    }

    public function test_stale_assignment_page_does_not_remove_a_new_membership(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $entry = $this->commission('Matériel', 'instruments');
        $page = Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user]);
        $entry->members()->attach($user->member_id);
        $page->call('save')->assertHasErrors('assignments');
        $this->assertTrue($entry->members()->where('members.id', $user->member_id)->exists());
    }

    public function test_private_archive_association_is_explicit_and_independent_of_module_write_access(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $entry = $this->commission('Archives', 'liturgique');
        $entry->update(['archive_commission' => 'commission_liturgique']);
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('archiveAssociates', [$entry->id])->call('save')->assertHasNoErrors();
        $this->assertTrue(Commission::Liturgique->canManageArchive($user));
        $this->assertFalse($user->canManageModule('liturgique'));
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('archiveAssociates', [])->set('members', [])->call('save')->assertHasNoErrors();
        $this->assertFalse(Commission::Liturgique->canManageArchive($user));
    }

    public function test_deleted_member_is_not_replaced_by_a_new_personal_record(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $member->delete();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Votre fiche choriste n’est plus disponible.');
        $this->actingAs($user)->patch('/profile', ['first_name' => 'New', 'last_name' => 'Record', 'email' => $user->email])->assertNotFound();
        $this->assertSame($member->id, $user->fresh()->member_id);
        $this->assertSame(1, Member::withTrashed()->count());
    }

    public function test_old_private_archive_associate_is_visible_in_the_owners_read_only_profile(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $user = User::factory()->create();
        $entry = $this->commission('Archives personnelles', 'liturgique');
        $entry->update(['archive_commission' => 'commission_liturgique']);
        $this->actingAs($admin);
        app(CommissionResponsibilities::class)->assign($entry->id, $admin->id, $user->id, 'liturgique');
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Associé désigné aux archives')->assertSee('Archives personnelles');
    }

    public function test_instrument_associate_checkbox_preserves_other_associates_and_can_be_revoked(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $other = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $entry = $this->commission('Matériel', 'instruments');
        $this->actingAs($admin);
        app(CommissionResponsibilities::class)->assign($entry->id, $admin->id, null, 'instruments', [$other->id]);
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('instrumentAssociates', [$entry->id])->call('save')->assertHasNoErrors();
        $this->assertTrue($user->canManageModule('instruments'));
        $this->assertTrue($entry->instrumentAssociates()->where('users.id', $other->id)->exists());
        Livewire::actingAs($admin)->test(CommissionAssignments::class, ['user' => $user])
            ->set('instrumentAssociates', [])->set('members', [])->call('save')->assertHasNoErrors();
        $this->assertFalse($user->canAccessModule('instruments'));
        $this->assertTrue($entry->instrumentAssociates()->where('users.id', $other->id)->exists());
        $this->assertSame($admin->id, $entry->fresh()->leader_id);
    }

    public function test_postgres_account_lock_does_not_block_unrelated_foreign_key_checks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL row-lock compatibility requires a disposable PostgreSQL database.');
        }
        app(TenantContext::class)->lockForAccountChanges();
        $pdo = (new PostgresConnector)->connect(config('database.connections.pgsql'));
        $pdo->beginTransaction();
        try {
            $pdo->exec("SET LOCAL lock_timeout = '500ms'");
            $statement = $pdo->prepare("INSERT INTO audit_events (tenant_id, actor_id, subject_type, subject_id, action, changes, created_at) VALUES (?, NULL, 'LockProbe', 0, 'updated', '{}', CURRENT_TIMESTAMP)");
            $statement->execute([app(TenantContext::class)->requireId()]);
            $this->assertSame(1, $statement->rowCount());
        } finally {
            $pdo->rollBack();
        }
    }
}
