<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Members\Access;
use App\Livewire\Members\Form;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use App\Services\MemberOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MemberAccessTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(): array
    {
        $member = Member::factory()->create(['first_name' => 'Jean Pierre', 'last_name' => 'Kouassi']);
        $url = app(MemberOnboarding::class)->issue($member, UserRole::Secretaire);

        return [$member, $url];
    }

    private function credentials(array $overrides = []): array
    {
        return array_merge(['username' => 'jean', 'email' => 'jean@example.test', 'password' => 'MonSecretPersonnel2026', 'password_confirmation' => 'MonSecretPersonnel2026'], $overrides);
    }

    public function test_admin_prepares_access_without_login_or_password_fields(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $component = Livewire::actingAs($admin)->test(Access::class, ['member' => $member])
            ->assertDontSeeHtml('name="password"')->assertDontSeeHtml('name="username"')
            ->set('role', 'secretaire')->call('save')->assertHasNoErrors();
        $this->assertNotEmpty($component->get('activationLink'));
        $this->assertNull($member->fresh()->user);
        $this->assertDatabaseHas('member_invitations', ['member_id' => $member->id, 'role' => 'secretaire']);
    }

    public function test_new_member_gets_an_invitation_without_email_or_account_creation(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $component = Livewire::actingAs($admin)->test(Form::class)->set('first_name', 'Éléonore Marie')->set('last_name', 'Amegan')->call('save')->assertHasNoErrors();
        $this->assertNotEmpty($component->get('activationLink'));
        $this->assertSame(1, MemberInvitation::count());
        $this->assertSame(1, User::count());
        $component->call('save');
        $this->assertSame(1, Member::count());
    }

    public function test_non_admin_cannot_issue_access_or_modify_roles(): void
    {
        $user = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create();
        Livewire::actingAs($user)->test(Access::class, ['member' => $member])->assertForbidden();
        Livewire::test(Form::class)->set('first_name', 'Jean')->set('last_name', 'Test')->call('save')->assertHasNoErrors()->assertDispatched('close-form');
        $this->assertDatabaseCount('member_invitations', 0);
    }

    public function test_existing_account_role_changes_preserve_all_personal_credentials(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'username' => 'personnel']);
        $before = $user->only('name', 'username', 'email', 'password');
        Livewire::actingAs($admin)->test(Access::class, ['member' => $member])->set('role', 'secretaire')->call('save')->assertHasNoErrors();
        $this->assertSame($before, $user->fresh()->only('name', 'username', 'email', 'password'));
        $this->assertSame(UserRole::Secretaire, $user->fresh()->role);
    }

    public function test_first_given_name_is_suggested_and_can_be_changed_by_the_member(): void
    {
        [$member, $url] = $this->invitation();
        $this->get($url)->assertOk()->assertViewHas('username', 'jean')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($url, $this->credentials(['username' => 'jean.personnel', 'role' => 'admin', 'tenant_id' => 999]))->assertRedirect(route('dashboard'));
        $user = $member->fresh()->user;
        $this->assertAuthenticatedAs($user);
        $this->assertSame('jean.personnel', $user->username);
        $this->assertSame(UserRole::Secretaire, $user->role);
        $this->assertTrue(Hash::check('MonSecretPersonnel2026', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertSame('jean@example.test', $member->fresh()->email);
    }

    public function test_same_first_names_get_a_suffix_and_accents_are_normalized(): void
    {
        User::factory()->create(['username' => 'eleonore']);
        $member = Member::factory()->create(['first_name' => 'Éléonore Marie']);
        $this->assertSame('eleonore.2', app(MemberOnboarding::class)->suggestUsername($member));
    }

    public function test_expiry_boundary_is_exact_and_does_not_create_an_account(): void
    {
        [$member, $url] = $this->invitation();
        $expiry = MemberInvitation::firstOrFail()->expires_at;
        $this->travelTo($expiry);
        $this->get($url)->assertGone()->assertSee('Ce lien n’est plus disponible');
        $this->post($url, $this->credentials())->assertGone();
        $this->assertNull($member->fresh()->user);
    }

    public function test_reissuing_an_invitation_invalidates_the_previous_link(): void
    {
        [$member, $old] = $this->invitation();
        $new = app(MemberOnboarding::class)->issue($member);
        $this->get($old)->assertGone();
        $this->get($new)->assertOk();
        $this->assertDatabaseCount('member_invitations', 1);
        $token = basename(parse_url($new, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $token), MemberInvitation::firstOrFail()->token_hash);
        $this->assertStringNotContainsString($token, json_encode(DB::table('audit_events')->get()));
        $this->assertStringNotContainsString($token, json_encode(MemberInvitation::firstOrFail()));
    }

    public function test_used_link_cannot_be_replayed_even_with_a_different_email(): void
    {
        [$member, $url] = $this->invitation();
        $this->post($url, $this->credentials())->assertRedirect();
        auth()->logout();
        $this->post($url, $this->credentials(['email' => 'intrus@example.test']))->assertGone();
        $this->assertSame(1, User::where('member_id', $member->id)->count());
    }

    public function test_validation_failure_keeps_invitation_usable_and_creates_no_account(): void
    {
        [$member, $url] = $this->invitation();
        $this->from($url)->post($url, $this->credentials(['password' => 'Jean', 'password_confirmation' => 'Jean']))->assertSessionHasErrors('password');
        $this->assertNull($member->fresh()->user);
        $this->assertNull(MemberInvitation::firstOrFail()->accepted_at);
        $this->post($url, $this->credentials())->assertRedirect(route('dashboard'));
    }

    public function test_duplicate_username_or_email_are_field_errors_and_do_not_consume_link(): void
    {
        [$member, $url] = $this->invitation();
        User::factory()->create(['username' => 'jean', 'email' => 'existing@example.test']);
        $this->post($url, $this->credentials(['email' => 'existing@example.test']))->assertSessionHasErrors(['username', 'email']);
        $this->assertNull(MemberInvitation::firstOrFail()->accepted_at);
        $this->assertNull($member->fresh()->user);
    }

    public function test_removed_member_or_revoked_invitation_cannot_activate(): void
    {
        [$member, $url] = $this->invitation();
        $member->delete();
        $this->get($url)->assertNotFound();
        $member->restore();
        app(MemberOnboarding::class)->revoke($member);
        $this->get($url)->assertGone();
    }

    public function test_admin_can_revoke_a_pending_access_without_a_user_account(): void
    {
        [$member, $url] = $this->invitation();
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Access::class, ['member' => $member])->call('revoke')->assertHasNoErrors();
        auth()->logout();
        $this->get($url)->assertGone();
        $this->assertDatabaseCount('member_invitations', 0);
    }

    public function test_malformed_and_unknown_tokens_reveal_no_member_information(): void
    {
        $this->get('/activer-mon-compte/invalid')->assertGone();
        $this->post('/activer-mon-compte/'.str_repeat('a', 64), $this->credentials())->assertGone();
        $this->assertGuest();
    }
}
