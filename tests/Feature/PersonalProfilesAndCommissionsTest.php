<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Commissions\Index;
use App\Livewire\Members\Form as MemberForm;
use App\Livewire\Users\Form as UserForm;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChoirCommissionWriter;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalProfilesAndCommissionsTest extends TestCase
{
    use RefreshDatabase;

    private function mission(array $extra = []): array
    {
        return array_replace(['name' => 'Concert de Noël', 'kind' => 'ad_hoc', 'mission' => 'Organiser le concert et les répétitions.', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(20)->toDateString(), 'member_ids' => []], $extra);
    }

    public function test_own_profile_updates_membership_and_account_atomically_without_role_escalation(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $this->actingAs($user)->patch('/profile', ['name' => 'Ignored', 'first_name' => 'Anne Marie', 'last_name' => 'Koffi', 'username' => ' ANNE ', 'email' => ' ANNE@EXAMPLE.TEST ', 'phone' => '+22890000000', 'notify_email' => 0, 'role' => 'admin', 'member_id' => 999])->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->assertSame('Anne Marie Koffi', $user->fresh()->name);
        $this->assertSame('anne', $user->fresh()->username);
        $this->assertSame(UserRole::Choriste, $user->fresh()->role);
        $this->assertSame($member->id, $user->fresh()->member_id);
        $this->assertSame('anne@example.test', $member->fresh()->email);
        $this->assertFalse($member->fresh()->notify_email);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Mes informations personnelles');
    }

    public function test_admin_cannot_change_another_persons_identity_or_password_but_can_change_role(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        Livewire::actingAs($admin)->test(UserForm::class, ['user' => $user])->set('name', 'Identity stolen')->call('save')->assertForbidden();
        Livewire::actingAs($admin)->test(UserForm::class, ['user' => $user])->set('password', 'SecretChanged123')->set('password_confirmation', 'SecretChanged123')->call('save')->assertForbidden();
        $this->assertNotSame('Identity stolen', $user->fresh()->name);
        Livewire::actingAs($admin)->test(UserForm::class, ['user' => $user])->set('role', 'secretaire')->call('save')->assertHasNoErrors();
        $this->assertSame(UserRole::Secretaire, $user->fresh()->role);
    }

    public function test_duplicate_profile_contact_does_not_partially_change_membership(): void
    {
        $member = Member::factory()->create(['first_name' => 'Anne']);
        $user = User::factory()->create(['member_id' => $member->id]);
        $other = Member::factory()->create(['email' => 'already@example.test']);
        $this->actingAs($user)->patch('/profile', ['name' => 'Ignored', 'first_name' => 'Changed', 'email' => $other->email])->assertSessionHasErrors('email');
        $this->assertSame('Anne', $member->fresh()->first_name);
        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_member_manager_can_change_voice_but_cannot_change_personal_details(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create(['birth_date' => '1990-01-01 15:30:00', 'voice' => 'soprano']);
        Livewire::actingAs($director)->test(MemberForm::class, ['member' => $member])->set('phone', '+22811111111')->call('save')->assertForbidden();
        Livewire::actingAs($director)->test(MemberForm::class, ['member' => $member->fresh()])->set('voice', 'alto')->call('save')->assertStatus(200)->assertHasNoErrors();
        $this->assertSame('alto', $member->fresh()->voice->value);
    }

    public function test_direction_can_update_membership_date_without_changing_personal_identity(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create(['joined_on' => '2020-01-10', 'birth_date' => '1990-01-01']);
        Livewire::actingAs($director)->test(MemberForm::class, ['member' => $member])
            ->assertSet('activationLink', null)
            ->set('joined_on', '2024-09-15')
            ->assertSet('activationLink', null)
            ->assertSet('joined_on', '2024-09-15')
            ->call('save')
            ->assertStatus(200)
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('2024-09-15', $member->fresh()->joined_on->format('Y-m-d'));
        $this->assertSame('1990-01-01', $member->fresh()->birth_date->format('Y-m-d'));
    }

    public function test_only_director_can_create_edit_or_archive_commissions_even_admin_is_denied(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create();
        $component = Livewire::actingAs($director)->test(Index::class)->call('create');
        foreach ($this->mission(['member_ids' => [$member->id]]) as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('save')->assertHasNoErrors();
        $entry = ChoirCommission::firstOrFail();
        $this->assertSame([$member->id], $entry->members->modelKeys());
        $this->assertSame('active', $entry->status);
        foreach ([UserRole::Admin, UserRole::Choriste, UserRole::Secretaire] as $role) {
            $other = User::factory()->role($role)->create();
            $this->actingAs($other)->get('/commissions')->assertOk()->assertDontSee('Créer une commission');
            Livewire::actingAs($other)->test(Index::class)->call('create')->assertForbidden();
            Livewire::actingAs($other)->test(Index::class)->call('edit', $entry->id)->assertForbidden();
            Livewire::actingAs($other)->test(Index::class)->call('toggleArchive', $entry->id)->assertForbidden();
            Livewire::actingAs($other)->test(Index::class)->call('save')->assertForbidden();
        }
        Livewire::actingAs($director)->test(Index::class)->call('edit', $entry->id)->assertSee($member->first_name)->set('mission', 'Mission précisée')->call('save')->assertHasNoErrors()->call('toggleArchive', $entry->id);
        $this->assertSame('archived', $entry->fresh()->status);
    }

    public function test_ad_hoc_requires_deadline_and_expires_after_last_day_without_deleting_team(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Directeur)->create());
        $component = Livewire::test(Index::class)->call('create')->set('name', 'Mission')->set('mission', 'Préparer le concert')->set('kind', 'ad_hoc');
        $component->call('save')->assertHasErrors(['ends_on']);
        $component->set('ends_on', today()->subDay()->toDateString())->call('save')->assertHasErrors(['ends_on']);
        $entry = app(ChoirCommissionWriter::class)->save(null, $this->mission(['ends_on' => today()->toDateString()]));
        $this->travelTo(today()->endOfDay());
        $this->assertSame('active', $entry->status);
        $this->assertSame(1, ChoirCommission::inStatus('active')->count());
        $this->travel(1)->seconds();
        $this->assertSame('completed', $entry->status);
        $this->assertSame(1, ChoirCommission::inStatus('completed')->count());
        $this->travelBack();
    }

    public function test_commission_name_is_unique_in_choir_and_membership_does_not_grant_roles(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $this->actingAs($director);
        $entry = app(ChoirCommissionWriter::class)->save(null, $this->mission(['member_ids' => [$member->id]]));
        Livewire::test(Index::class)->call('create')->set('name', 'CONCERT DE NOËL')->set('mission', 'Autre mission')->call('save')->assertHasErrors(['name']);
        $this->assertSame(UserRole::Choriste, $user->fresh()->role);
        Livewire::actingAs($user)->test(Index::class)->set('mine', true)->assertSee($entry->name)->call('view', $entry->id)->assertSee('Choristes affectés');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('Commission');
    }

    public function test_foreign_chorales_and_members_are_not_accessible(): void
    {
        config(['tenancy.enabled' => true]);
        $a = Tenant::forceCreate(['name' => 'Alpha', 'slug' => 'alpha', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $b = Tenant::forceCreate(['name' => 'Beta', 'slug' => 'beta', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $ctx = app(TenantContext::class);
        [$foreign, $foreignCommission] = $ctx->run($b->id, fn () => [Member::factory()->create(), ChoirCommission::create(['name' => 'Équipe Beta', 'name_key' => 'équipe beta', 'kind' => 'permanent', 'mission' => 'Mission confidentielle Beta', 'starts_on' => today()])]);
        $director = $ctx->run($a->id, fn () => User::factory()->role(UserRole::Directeur)->create());
        $this->actingAs($director);
        $ctx->run($a->id, function () use ($foreign, $foreignCommission, $a) {
            $component = Livewire::test(Index::class)->call('create');
            foreach ($this->mission(['member_ids' => [$foreign->id]]) as $key => $value) {
                $component->set($key, $value);
            }
            $component->call('save')->assertHasErrors(['member_ids.0']);
            $this->assertSame(0, ChoirCommission::count());
            Livewire::test(Index::class)->call('view', $foreignCommission->id)->assertNotFound();
            Livewire::test(Index::class)->call('edit', $foreignCommission->id)->assertNotFound();
            Livewire::test(Index::class)->call('toggleArchive', $foreignCommission->id)->assertNotFound();
            $own = app(ChoirCommissionWriter::class)->save(null, $this->mission());
            $this->expectException(QueryException::class);
            DB::transaction(fn () => DB::table('choir_commission_member')->insert(['tenant_id' => $a->id, 'choir_commission_id' => $own->id, 'member_id' => $foreign->id]));
        });
    }
}
