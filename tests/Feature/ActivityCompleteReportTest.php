<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Participants;
use App\Livewire\Attendance\Sheet;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MemberExpenseWriter;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class ActivityCompleteReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_open_an_activity_from_its_link_and_build_an_empty_roster(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $member = Member::factory()->active()->create(['first_name' => 'Afi']);
        $activity = Activity::factory()->create(['title' => 'Sortie paroissiale', 'participation_fee_enabled' => true, 'participation_fee_amount' => 2500]);
        $this->actingAs($secretary)->get(route('attendance.sheet', ['type' => 'activity', 'evenement' => $activity->id]))
            ->assertOk()->assertSee('Sortie paroissiale')->assertSee('Choristes actifs et intervenants disponibles')->assertSee('Ajouter à la fiche');
        Livewire::actingAs($secretary)->withQueryParams(['type' => 'activity', 'evenement' => $activity->id])->test(Sheet::class)
            ->assertSet('eventType', 'activity')->assertSet('eventId', $activity->id)->assertCount('rows', 0)
            ->set('selectedMemberIds', [(string) $member->id])->call('addSelectedMembers')->assertHasNoErrors()
            ->assertCount('rows', 1)->assertSet('rows.0.status', '');
        $this->assertDatabaseHas('activity_participants', ['activity_id' => $activity->id, 'member_id' => $member->id, 'status' => 'invite']);
        $this->assertDatabaseHas('contributions', ['activity_id' => $activity->id, 'member_id' => $member->id, 'due_amount' => 2500]);
        $this->get(route('attendance.report', ['activite', $activity->id, 'document' => 'fiche']))
            ->assertOk()->assertSee('Fiche de présence')->assertSee('Afi')->assertSee('Signature')->assertDontSee('Point financier');
    }

    public function test_adding_members_preserves_unsaved_statuses_and_does_not_duplicate_fees(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $first = Member::factory()->active()->create(['last_name' => 'Alpha']);
        $second = Member::factory()->active()->create(['last_name' => 'Beta']);
        $activity = Activity::factory()->create(['participation_fee_enabled' => true, 'participation_fee_amount' => 1000]);
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $first->id, 'status' => 'invite']);
        $component = Livewire::actingAs($secretary)->test(Sheet::class)->set('eventType', 'activity')->set('eventId', $activity->id)
            ->set('rows.0.status', 'late')->set('rows.0.minutes_late', '12')
            ->set('selectedMemberIds', [(string) $second->id])->call('addSelectedMembers')->assertHasNoErrors()
            ->assertSet('rows.0.status', 'late')->assertSet('rows.0.minutes_late', '12')->assertCount('rows', 2);
        $component->set('selectedMemberIds', [(string) $second->id])->call('addSelectedMembers')->assertHasNoErrors();
        $this->assertSame(2, $activity->participantRecords()->count());
        $this->assertSame(1, Contribution::query()->where('activity_id', $activity->id)->where('member_id', $second->id)->count());
    }

    public function test_archived_activity_members_and_old_rehearsal_rows_remain_readable(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $archived = Member::factory()->active()->create(['first_name' => 'Ancien', 'last_name' => 'Choriste']);
        $activity = Activity::factory()->create(['attendance_finalized_at' => now()]);
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $archived->id, 'status' => 'present']);
        $rehearsal = Rehearsal::factory()->create(['attendance_finalized_at' => now()]);
        RehearsalAttendance::query()->create(['rehearsal_id' => $rehearsal->id, 'member_id' => $archived->id, 'status' => 'present']);
        $archived->delete();
        Member::factory()->active()->create(['first_name' => 'Nouveau']);
        foreach ([['activity', 'activite', $activity->id], ['rehearsal', 'repetition', $rehearsal->id]] as [$type, $routeType, $id]) {
            Livewire::actingAs($secretary)->test(Sheet::class)->set('eventType', $type)->set('eventId', $id)
                ->assertCount('rows', 1)->assertSee('Ancien Choriste')->assertDontSee('Nouveau');
            $this->actingAs($secretary)->get(route('attendance.report', [$routeType, $id]))
                ->assertOk()->assertSee('Ancien Choriste')->assertDontSee('Nouveau');
        }
    }

    public function test_a_blank_rehearsal_sheet_lists_expected_members_without_inventing_absences(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $rehearsal = Rehearsal::factory()->create();
        $member = Member::factory()->active()->create(['first_name' => 'À pointer']);
        $this->actingAs($secretary)->get(route('attendance.report', ['repetition', $rehearsal->id, 'document' => 'fiche']))
            ->assertOk()->assertSee('À pointer')->assertSee('Non renseigné');
        $this->assertDatabaseCount('rehearsal_attendances', 0);
    }

    public function test_a_finalized_activity_cannot_be_changed_through_either_roster_screen(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->active()->create();
        $activity = Activity::factory()->create();
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $member->id, 'status' => 'present']);
        $participantScreen = Livewire::actingAs($admin)->test(Participants::class, ['activity' => $activity]);
        $sheet = Livewire::actingAs($admin)->test(Sheet::class)->set('eventType', 'activity')->set('eventId', $activity->id);
        $activity->update(['attendance_finalized_at' => now()]);
        $participantScreen->call('remove', $member->id)->assertStatus(422);
        $newMember = Member::factory()->active()->create();
        $sheet->set('selectedMemberIds', [(string) $newMember->id])->call('addSelectedMembers')->assertStatus(422);
        $this->assertSame(1, $activity->participantRecords()->count());
    }

    public function test_the_complete_activity_report_combines_all_saved_sections_with_separate_financial_totals(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->active()->create(['first_name' => 'Alice', 'last_name' => 'Mensah']);
        $activity = Activity::factory()->create(['title' => 'Concert complet', 'description' => 'Description détaillée',
            'report' => 'Compte rendu signé', 'budget_allocated' => 50000, 'budget_spent' => 12000, 'attendance_finalized_at' => now()]);
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $member->id,
            'status' => 'present', 'role' => 'soliste', 'hours' => 2.5, 'notes' => 'Participation bénévole']);
        Transaction::factory()->recette()->create(['activity_id' => $activity->id, 'amount' => 10000, 'description' => 'Billets validés']);
        Transaction::factory()->depense()->create(['activity_id' => $activity->id, 'amount' => 2000, 'description' => 'Transport validé']);
        Transaction::factory()->create(['activity_id' => $activity->id, 'status' => 'brouillon', 'type' => 'depense', 'amount' => 99999, 'description' => 'Facture non validée']);
        $this->actingAs($admin);
        $advance = app(MemberExpenseWriter::class)->create(['member_id' => $member->id, 'activity_id' => $activity->id,
            'incurred_on' => today()->toDateString(), 'description' => 'Avance pour décor', 'amount' => 3000]);
        app(MemberExpenseWriter::class)->decide($advance->id, 'approved', 'Décision du bureau');
        Contribution::factory()->create(['activity_id' => $activity->id, 'member_id' => $member->id, 'due_amount' => 5000, 'paid_amount' => 2000]);
        $response = $this->get(route('activities.detail-report', $activity));
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Rapport complet de l’activité')->assertSee('Description détaillée')->assertSee('Compte rendu signé')
            ->assertSee('Alice Mensah')->assertSee('Soliste')->assertSee('Participation bénévole')->assertSee('2,50')
            ->assertSee('Billets validés')->assertSee('Facture non validée')->assertSee('Avance pour décor')->assertSee('Participations financières');
        $this->assertEquals(10000, $response->viewData('finance')['receipts']);
        $this->assertEquals(2000, $response->viewData('finance')['expenses']);
        $this->assertSame('landscape', $response->viewData('orientation'));
    }

    public function test_draft_finances_can_be_read_without_exposing_unvalidated_attendance_to_accounting(): void
    {
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();
        $activity = Activity::factory()->create();
        $member = Member::factory()->active()->create(['first_name' => 'Présence privée']);
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $member->id, 'status' => 'late', 'minutes_late' => 12]);
        Transaction::factory()->recette()->create(['activity_id' => $activity->id, 'description' => 'Recette accessible']);
        $this->actingAs($treasurer)->get(route('activities.detail-report', $activity))->assertOk()
            ->assertSee('Recette accessible')->assertDontSee('Présence privée')->assertSee('en cours de saisie');
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $this->actingAs($secretary)->get(route('activities.detail-report', $activity))->assertOk()
            ->assertSee('Présence privée')->assertDontSee('Recette accessible');
        $chorister = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($chorister)->get(route('activities.detail-report', $activity))->assertForbidden();
    }

    public function test_the_complete_report_and_the_sheet_are_discoverable_from_the_activity(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $activity = Activity::factory()->create();
        $this->actingAs($secretary)->get(route('activities.index'))->assertOk()
            ->assertSee(route('activities.detail-report', $activity), false)
            ->assertSee(route('attendance.sheet', ['type' => 'activity', 'evenement' => $activity->id]));
    }

    public function test_the_activity_report_cannot_be_read_from_another_choir(): void
    {
        config(['tenancy.enabled' => true]);
        Notification::fake();
        $registration = app(TenantRegistration::class);
        $alpha = $registration->create(['organization' => 'Alpha', 'name' => 'Alpha', 'username' => 'alpha', 'email' => 'alpha@example.test', 'password' => 'SecretChorale2026']);
        $beta = $registration->create(['organization' => 'Beta', 'name' => 'Beta', 'username' => 'beta', 'email' => 'beta@example.test', 'password' => 'SecretChorale2026']);
        $foreign = app(TenantContext::class)->run($beta->tenant_id, fn () => Activity::factory()->create());
        $this->actingAs($alpha)->get(route('activities.detail-report', $foreign->id))->assertNotFound();
        $this->get(route('attendance.sheet', ['type' => 'activity', 'evenement' => $foreign->id]))->assertNotFound();
    }
}
