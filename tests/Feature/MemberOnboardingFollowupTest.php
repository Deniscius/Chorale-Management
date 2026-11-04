<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Onboarding\Index;
use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\MemberOnboardingFollowup;
use App\Models\User;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class MemberOnboardingFollowupTest extends TestCase
{
    use RefreshDatabase;

    public function test_followup_leader_can_record_an_onboarding_step_without_exposing_private_note_in_audit(): void
    {
        $member = Member::factory()->create();
        $leader = User::factory()->role(UserRole::CommissionSuivieFormation)->create();

        Livewire::actingAs($leader)->test(Index::class)
            ->call('edit', $member->id)
            ->set('status', 'en_accompagnement')
            ->set('contactedOn', today()->toDateString())
            ->set('note', 'Premier accueil effectué')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('member_onboarding_followups', [
            'member_id' => $member->id,
            'status' => 'en_accompagnement',
            'updated_by' => $leader->id,
        ]);
        $events = AuditEvent::query()->where('subject_id', $member->id)->where('action', 'updated')->get();
        $this->assertTrue($events->isNotEmpty());
        $this->assertStringNotContainsString('Premier accueil effectué', $events->toJson());
    }

    public function test_follow_up_cannot_open_a_member_from_another_tenant(): void
    {
        config(['tenancy.enabled' => true]);
        $a = app(TenantRegistration::class)->create([
            'organization' => 'Chorale accueil A', 'name' => 'Admin A',
            'username' => 'onboarding-admin-a', 'email' => 'onboarding-a@example.test',
            'password' => 'TestingPassword2026!',
        ]);
        $b = app(TenantRegistration::class)->create([
            'organization' => 'Chorale accueil B', 'name' => 'Admin B',
            'username' => 'onboarding-admin-b', 'email' => 'onboarding-b@example.test',
            'password' => 'TestingPassword2026!',
        ]);

        $memberId = app(TenantContext::class)->run($b->tenant_id, fn () => Member::factory()->create()->id);
        app(TenantContext::class)->run($a->tenant_id, function () use ($memberId): void {
            $leader = User::factory()->role(UserRole::CommissionSuivieFormation)->create();
            Livewire::actingAs($leader)->test(Index::class)
                ->call('edit', $memberId)
                ->assertNotFound();
        });
    }

    public function test_chorister_cannot_access_or_update_followups(): void
    {
        $member = Member::factory()->create();
        $chorister = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($chorister)->get('/choristes/accueil-et-suivi')->assertForbidden();
        Livewire::actingAs($chorister)->test(Index::class)->assertForbidden();
        $this->assertSame(0, MemberOnboardingFollowup::count());
    }
}
