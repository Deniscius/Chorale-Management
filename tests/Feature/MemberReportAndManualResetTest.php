<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Users\Index;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class MemberReportAndManualResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_report_contains_the_complete_tenant_roster_and_print_controls(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Mensah', 'voice' => 'soprano']);
        Member::factory()->create(['first_name' => 'Basile', 'last_name' => 'Koffi', 'voice' => 'bass']);

        $this->actingAs($director)->get(route('members.report'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Rapport général des choristes')
            ->assertSee('Alice Mensah')
            ->assertSee('Basile Koffi')
            ->assertSee('Imprimer / enregistrer en PDF');

        $chorister = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($chorister)->get(route('members.report'))->assertForbidden();
    }

    public function test_admin_generates_a_one_time_reset_link_after_password_confirmation(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $target = User::factory()->role(UserRole::Choriste)->create(['email' => 'choriste@example.test']);

        $component = Livewire::actingAs($admin)->test(Index::class)
            ->call('openPasswordReset', $target->id)
            ->set('currentPassword', 'password')
            ->call('generatePasswordResetLink')
            ->assertHasNoErrors();

        $link = $component->get('generatedResetLink');
        $this->assertStringContainsString('/reset-password/', $link);
        $this->assertStringNotContainsString(parse_url($link, PHP_URL_PATH), (string) DB::table('password_reset_tokens')->value('token'));
        $this->assertStringNotContainsString($link, json_encode(DB::table('audit_events')->get()));

        parse_str(parse_url($link, PHP_URL_QUERY) ?: '', $query);
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'NouveauSecret2026',
            'password_confirmation' => 'NouveauSecret2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NouveauSecret2026', $target->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_wrong_admin_password_creates_no_reset_token(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('openPasswordReset', $target->id)
            ->set('currentPassword', 'incorrect')
            ->call('generatePasswordResetLink')
            ->assertHasErrors('currentPassword')
            ->assertSet('generatedResetLink', '');
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_public_reset_does_not_create_or_log_a_token_without_real_mail_delivery(): void
    {
        config(['mail.delivery_enabled' => false]);
        Notification::fake();
        $user = User::factory()->create();

        $this->get(route('password.request'))->assertOk()->assertSee('Contactez un administrateur');
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'manual-reset-required');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
