<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Accounting\MemberOverview;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\AttendanceIncident;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class MemberDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_chorister_sees_only_their_own_complete_dossier(): void
    {
        $member = Member::factory()->active()->create(['first_name' => 'Alice', 'last_name' => 'Mensah']);
        $other = Member::factory()->active()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $rehearsal = Rehearsal::factory()->create(['date' => today(), 'venue' => 'Salle A']);
        RehearsalAttendance::query()->create([
            'rehearsal_id' => $rehearsal->id, 'member_id' => $member->id,
            'status' => 'late', 'minutes_late' => 12, 'checked_at' => now(),
        ]);
        Contribution::factory()->create([
            'member_id' => $member->id, 'due_amount' => 5000, 'paid_amount' => 3000, 'status' => 'partiel',
        ]);

        $this->actingAs($user)->get(route('members.show', $member))
            ->assertOk()->assertSee('Alice Mensah')->assertSee('12 min de retard')->assertSee('5 000 FCFA');
        $this->actingAs($user)->get(route('members.show', $other))->assertForbidden();
    }

    public function test_secretary_sees_attendance_but_not_financial_amounts(): void
    {
        $member = Member::factory()->active()->create();
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $activity = Activity::factory()->create(['title' => 'Concert annuel', 'starts_at' => now()]);
        ActivityParticipant::query()->create([
            'activity_id' => $activity->id, 'member_id' => $member->id,
            'status' => 'absent', 'checked_at' => now(),
        ]);
        Contribution::factory()->create(['member_id' => $member->id, 'due_amount' => 7777]);

        $this->actingAs($secretary)->get(route('members.show', $member))
            ->assertOk()->assertSee('Concert annuel')->assertDontSee('7 777')->assertDontSee('Situation financière personnelle');
    }

    public function test_accounting_can_filter_the_general_view_by_voice_and_member(): void
    {
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();
        $soprano = Member::factory()->voice('soprano')->create(['first_name' => 'Sophie', 'last_name' => 'Voix']);
        $bass = Member::factory()->voice('bass')->create(['first_name' => 'Basile', 'last_name' => 'Voix']);
        Contribution::factory()->create(['member_id' => $soprano->id, 'due_amount' => 4000, 'paid_amount' => 1000, 'status' => 'partiel']);
        AttendanceIncident::query()->create([
            'member_id' => $soprano->id, 'source_type' => 'rehearsal', 'occurred_on' => today(),
            'event_label' => 'Répétition', 'incident_type' => 'absence', 'amount' => 500, 'status' => 'pending',
        ]);

        $component = Livewire::actingAs($treasurer)->test(MemberOverview::class)
            ->assertSee('Sophie Voix')->assertSee('Basile Voix')
            ->set('voice', 'soprano')->assertSee('Sophie Voix')
            ->set('memberId', (string) $soprano->id)
            ->assertSet('memberId', (string) $soprano->id)
            ->assertSee('3 000');
        $this->assertSame([$soprano->id], $component->instance()->rows->getCollection()->modelKeys());
    }

    public function test_foreign_tenant_member_is_not_resolved_in_the_dossier(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $foreign = Tenant::unguarded(fn () => Tenant::query()->create([
            'name' => 'Autre chorale', 'slug' => 'autre-chorale',
            'trial_started_at' => now(), 'trial_ends_at' => now()->addDay(),
        ]));
        config(['tenancy.enabled' => true]);
        $foreignId = app(TenantContext::class)->run($foreign->id, fn () => Member::factory()->create()->id);

        $this->actingAs($director)->get('/membres/'.$foreignId.'/dossier')->assertNotFound();
    }
}
