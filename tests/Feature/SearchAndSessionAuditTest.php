<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Audit\Index;
use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SearchAndSessionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_search_matches_first_and_last_name_in_any_order(): void
    {
        $found = Member::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Mensah']);
        Member::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Autre']);

        $this->assertSame([$found->id], Member::search('Mensah Sophie')->pluck('id')->all());
    }

    public function test_successful_login_and_logout_are_audited_without_secret_data(): void
    {
        $user = User::factory()->create(['username' => 'audit-login']);
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect();
        $this->post('/logout')->assertRedirect('/');

        $events = AuditEvent::query()->where('actor_id', $user->id)
            ->whereIn('action', ['login', 'logout'])->orderBy('id')->get();
        $this->assertSame(['login', 'logout'], $events->pluck('action')->all());
        $this->assertStringNotContainsString('password', $events->toJson());
    }

    public function test_audit_filters_are_restricted_to_administrators(): void
    {
        $memberUser = User::factory()->role(UserRole::Choriste)->create();
        Livewire::actingAs($memberUser)->test(Index::class)->assertForbidden();

        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Index::class)
            ->set('action', 'login')->assertHasNoErrors()
            ->set('search', $admin->name)->assertHasNoErrors();
    }
}
