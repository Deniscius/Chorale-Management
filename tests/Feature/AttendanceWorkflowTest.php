<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Attendance\Sheet;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\AttendanceIncident;
use App\Models\AttendanceRule;
use App\Models\LetterheadTemplate;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\User;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_can_open_attendance_but_chorister_cannot(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $chorister = User::factory()->role(UserRole::Choriste)->create();

        $this->actingAs($secretary)->get(route('attendance.sheet'))->assertOk();
        $this->actingAs($chorister)->get(route('attendance.sheet'))->assertForbidden();
    }

    public function test_draft_never_turns_unmarked_member_into_an_absence(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $rehearsal = Rehearsal::factory()->create(['date' => today()]);
        Member::factory()->active()->create();

        Livewire::actingAs($secretary)->test(Sheet::class)
            ->set('eventId', $rehearsal->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('rehearsal_attendances', 0);
        $this->assertDatabaseCount('attendance_incidents', 0);
    }

    public function test_finalization_reports_only_unexcused_absence_and_uses_amount_snapshot(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $absent = Member::factory()->active()->create(['last_name' => 'Absent']);
        $excused = Member::factory()->active()->create(['last_name' => 'Excusé']);
        $rehearsal = Rehearsal::factory()->create(['date' => today(), 'venue' => 'Salle A']);
        AttendanceRule::query()->create([
            'financial_tracking_enabled' => true,
            'rehearsal_absence_amount' => 1500,
            'activity_absence_amount' => 2000,
            'late_amount' => 500,
            'late_threshold_minutes' => 10,
            'effective_from' => today()->subDay(),
        ]);

        $component = Livewire::actingAs($secretary)->test(Sheet::class)
            ->set('eventId', $rehearsal->id);
        $rows = collect($component->get('rows'));
        $absentIndex = $rows->search(fn ($row) => $row['member_id'] === $absent->id);
        $excusedIndex = $rows->search(fn ($row) => $row['member_id'] === $excused->id);

        $component
            ->set("rows.{$absentIndex}.status", 'absent')
            ->set("rows.{$excusedIndex}.status", 'excuse')
            ->set("rows.{$excusedIndex}.excuse_reason", 'Justificatif accepté')
            ->call('finalize')
            ->assertHasNoErrors();

        $this->assertNotNull($rehearsal->fresh()->attendance_finalized_at);
        $this->assertDatabaseHas('attendance_incidents', [
            'member_id' => $absent->id,
            'incident_type' => 'absence',
            'amount' => 1500,
            'status' => AttendanceIncident::PENDING,
        ]);
        $this->assertDatabaseMissing('attendance_incidents', ['member_id' => $excused->id]);
    }

    public function test_lateness_below_threshold_is_not_reported_and_reopening_cancels_incidents(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $late = Member::factory()->active()->create(['last_name' => 'Retard']);
        $absent = Member::factory()->active()->create(['last_name' => 'Absent']);
        $rehearsal = Rehearsal::factory()->create(['date' => today()]);
        AttendanceRule::query()->create(['late_threshold_minutes' => 15]);

        $component = Livewire::actingAs($secretary)->test(Sheet::class)->set('eventId', $rehearsal->id);
        $rows = collect($component->get('rows'));
        $lateIndex = $rows->search(fn ($row) => $row['member_id'] === $late->id);
        $absentIndex = $rows->search(fn ($row) => $row['member_id'] === $absent->id);
        $component->set("rows.{$lateIndex}.status", 'late')->set("rows.{$lateIndex}.minutes_late", '10')
            ->set("rows.{$absentIndex}.status", 'absent')->call('finalize')->assertHasNoErrors();

        $this->assertDatabaseMissing('attendance_incidents', ['member_id' => $late->id]);
        $this->assertDatabaseHas('attendance_incidents', ['member_id' => $absent->id, 'status' => 'pending']);

        $component->call('reopen')->assertHasNoErrors();
        $this->assertNull($rehearsal->fresh()->attendance_finalized_at);
        $this->assertDatabaseHas('attendance_incidents', ['member_id' => $absent->id, 'status' => 'cancelled']);
    }

    public function test_treasurer_sees_financial_report_but_not_private_attendance_sheet(): void
    {
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();

        $this->actingAs($treasurer)->get(route('attendance.financial-report'))->assertOk();
        $this->actingAs($treasurer)->get(route('attendance.sheet'))->assertForbidden();
    }

    public function test_a_finalized_sheet_without_incident_is_visible_to_accounting(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();
        Member::factory()->active()->create();
        $rehearsal = Rehearsal::factory()->create(['date' => today(), 'venue' => 'Salle paroissiale']);

        Livewire::actingAs($secretary)->test(Sheet::class)
            ->set('eventId', $rehearsal->id)
            ->call('markAllPresent')
            ->call('finalize')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('attendance_incidents', 0);
        $this->actingAs($treasurer)->get(route('attendance.financial-report'))
            ->assertOk()
            ->assertSee('Salle paroissiale')
            ->assertSee(route('attendance.report', ['repetition', $rehearsal->id]), false);
    }

    public function test_the_printable_attendance_report_uses_the_active_letterhead(): void
    {
        Storage::fake('local');
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $member = Member::factory()->active()->create(['first_name' => 'Alice', 'last_name' => 'Mensah']);
        $rehearsal = Rehearsal::factory()->create([
            'date' => today(), 'venue' => 'Grande salle',
            'attendance_finalized_at' => now(), 'attendance_finalized_by' => $secretary->id,
        ]);
        RehearsalAttendance::query()->create([
            'rehearsal_id' => $rehearsal->id, 'member_id' => $member->id,
            'status' => 'present', 'checked_at' => now(), 'recorded_by' => $secretary->id,
        ]);
        $path = app(TenantContext::class)->path('letterheads/official/header.png');
        app(PrivateFiles::class)->storeContents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            'image/png',
        );
        LetterheadTemplate::query()->create([
            'original_name' => 'papier-officiel.docx', 'header_path' => $path,
            'status' => LetterheadTemplate::STATUS_ACTIVE, 'activated_at' => now(), 'uploaded_by' => $secretary->id,
        ]);

        $this->actingAs($secretary)->get(route('attendance.report', ['repetition', $rehearsal->id]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('Alice')
            ->assertSee('Rapport de présence');
    }

    public function test_finalized_activity_uses_only_registered_participants(): void
    {
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $participant = Member::factory()->active()->create();
        Member::factory()->active()->create();
        $activity = Activity::factory()->create(['title' => 'Sortie', 'starts_at' => now()]);
        ActivityParticipant::query()->create([
            'activity_id' => $activity->id,
            'member_id' => $participant->id,
            'status' => 'invite',
        ]);

        Livewire::actingAs($secretary)->test(Sheet::class)
            ->set('eventType', 'activity')
            ->set('eventId', $activity->id)
            ->assertCount('rows', 1)
            ->set('rows.0.status', 'absent')
            ->call('finalize')
            ->assertHasNoErrors();

        $this->assertNotNull($activity->fresh()->attendance_finalized_at);
        $this->assertDatabaseHas('attendance_incidents', [
            'member_id' => $participant->id,
            'source_type' => 'activity',
            'incident_type' => 'absence',
        ]);
    }
}
