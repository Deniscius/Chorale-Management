<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Participants;
use App\Livewire\Attendance\Sheet;
use App\Livewire\Contributions\Form;
use App\Models\Activity;
use App\Models\AttendanceRule;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\User;
use App\Services\AttendanceIncidentManager;
use App\Services\ContributionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ContributionEditingAndExternalMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unpaid_contribution_can_be_edited_without_duplicating_it(): void
    {
        $user = User::factory()->role(UserRole::Tresorier)->create();
        $member = Member::factory()->active()->create();
        $contribution = Contribution::factory()->create(['member_id' => $member->id, 'paid_amount' => 0, 'paid_on' => null, 'status' => 'due']);
        Livewire::actingAs($user)->test(Form::class, ['contribution' => $contribution])
            ->set('due_amount', '3500')->set('notes', 'Montant corrigé')->call('save')->assertHasNoErrors();
        $this->assertSame('3500.00', $contribution->refresh()->due_amount);
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_a_historical_payment_allows_notes_without_creating_another_receipt(): void
    {
        $user = User::factory()->role(UserRole::Tresorier)->create();
        $member = Member::factory()->active()->create();
        $contribution = Contribution::factory()->create(['member_id' => $member->id, 'due_amount' => 5000, 'paid_amount' => 1000, 'paid_on' => '2026-05-12 15:35:48', 'status' => 'partiel']);
        Livewire::actingAs($user)->test(Form::class, ['contribution' => $contribution])
            ->set('notes', 'Justificatif retrouvé')->call('save')->assertHasNoErrors();
        $this->assertSame('Justificatif retrouvé', $contribution->refresh()->notes);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_external_helpers_can_be_enrolled_without_automatic_choir_dues(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create(['status' => 'externe']);
        $activity = Activity::factory()->create(['participation_fee_enabled' => true, 'participation_fee_amount' => 1000]);
        Livewire::actingAs($user)->test(Participants::class, ['activity' => $activity])
            ->assertSee($member->full_name)->set('selectedMemberIds', [(string) $member->id])->call('addSelected')->assertHasNoErrors();
        $this->assertDatabaseHas('activity_participants', ['activity_id' => $activity->id, 'member_id' => $member->id]);
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_external_registration_does_not_issue_a_chorister_account(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(\App\Livewire\Members\Form::class, ['initialStatus' => 'externe'])
            ->assertSet('status', 'externe')->set('first_name', 'Marie')->set('last_name', 'Soutien')
            ->call('save')->assertHasNoErrors()->assertSet('activationLink', null);
        $member = Member::query()->where('last_name', 'Soutien')->firstOrFail();
        $this->assertSame('externe', $member->status->value);
        $this->assertNull($member->user);
        $this->assertDatabaseCount('member_invitations', 0);
        app(ContributionSchedule::class)->sync(CarbonImmutable::today()->addMonths(2));
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_external_helpers_can_be_added_from_the_presence_sheet(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $member = Member::factory()->create(['status' => 'externe']);
        $activity = Activity::factory()->create();
        Livewire::actingAs($secretary)->test(Sheet::class)
            ->set('eventType', 'activity')->set('eventId', $activity->id)
            ->set('selectedMemberIds', [(string) $member->id])->call('addSelectedMembers')->assertHasNoErrors()->assertCount('rows', 1);
        $this->actingAs($secretary)->get(route('activities.detail-report', $activity))->assertOk()->assertSee('Intervenant extérieur');
    }

    public function test_external_helpers_do_not_receive_automatic_attendance_charges(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);
        $external = Member::factory()->create(['status' => 'externe']);
        $regular = Member::factory()->active()->create();
        $activity = Activity::factory()->create();
        AttendanceRule::query()->create(['financial_tracking_enabled' => true, 'activity_absence_amount' => 500]);
        foreach ([$external, $regular] as $member) {
            $activity->participantRecords()->create(['member_id' => $member->id, 'status' => 'absent']);
        }
        app(AttendanceIncidentManager::class)->finalizeActivity($activity);
        $this->assertDatabaseMissing('attendance_incidents', ['member_id' => $external->id]);
        $this->assertDatabaseHas('attendance_incidents', ['member_id' => $regular->id, 'amount' => 500]);
    }
}
