<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Choir;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalRhythm;
use App\Models\User;
use App\Enums\UserRole;
use App\Livewire\Rehearsals\Rhythms;
use Livewire\Livewire;
use App\Services\PersonalNotebook;
use App\Services\RehearsalPlanner;
use App\Services\RehearsalRhythmRemoval;
use App\Services\RehearsalRhythmSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RehearsalRhythmNotebookSyncTest extends TestCase
{
    use RefreshDatabase;

    private function rhythm(): RehearsalRhythm
    {
        return RehearsalRhythm::create([
            'choir_id' => Choir::factory()->create()->id,
            'weekday' => 6,
            'starts_at' => '15:00',
            'ends_at' => '18:00',
            'venue' => 'Salle A',
            'effective_from' => '2026-11-01',
            'is_active' => true,
        ]);
    }

    public function test_changing_weekday_updates_future_notebook_appointments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $member = Member::factory()->active()->create();
        $rhythm = $this->rhythm();
        $this->assertSame(2, app(RehearsalPlanner::class)->generate(
            CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-15')
        ));

        $before = $rhythm->getRawOriginal();
        $rhythm->update(['weekday' => 7, 'starts_at' => '10:00', 'ends_at' => '12:00', 'venue' => 'Salle B']);
        $counts = app(RehearsalRhythmSynchronizer::class)->synchronize($rhythm, $before);

        $this->assertSame(2, $counts['cancelled']);
        $this->assertSame(0, $counts['protected']);
        $this->assertSame(0, Rehearsal::query()->whereDate('date', '2026-11-07')->where('status', 'planifiee')->count());

        $notebook = app(PersonalNotebook::class)->for($member);
        $times = $notebook['timeline']->where('kind', 'rehearsal')->pluck('at')
            ->map(fn ($date) => $date->format('Y-m-d H:i'))->all();
        $this->assertContains('2026-11-08 10:00', $times);
        $this->assertNotContains('2026-11-07 15:00', $times);
        $this->assertSame('Salle B', Rehearsal::query()
            ->whereDate('date', '2026-11-08')->where('status', 'planifiee')->firstOrFail()->venue);
    }

    public function test_changing_time_updates_untouched_sessions_but_preserves_manual_overrides(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $rhythm = $this->rhythm();
        app(RehearsalPlanner::class)->generate(CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-14'));
        $manual = Rehearsal::query()->whereDate('date', '2026-11-07')->firstOrFail();
        $manual->update(['venue' => 'Salle spéciale']);

        $before = $rhythm->getRawOriginal();
        $rhythm->update(['starts_at' => '16:00', 'venue' => 'Salle B']);
        $counts = app(RehearsalRhythmSynchronizer::class)->synchronize($rhythm, $before);

        $this->assertSame(1, $counts['protected']);
        $this->assertSame('Salle spéciale', $manual->fresh()->venue);
        $this->assertSame('15:00', $manual->fresh()->starts_at);
        $this->assertSame('16:00', Rehearsal::query()
            ->whereDate('date', '2026-11-14')->where('rehearsal_rhythm_id', $rhythm->id)->firstOrFail()->starts_at);
    }

    public function test_legacy_reconciliation_repairs_old_appointments_without_overwriting_manually_edited_sessions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $member = Member::factory()->active()->create();
        $rhythm = $this->rhythm();
        app(RehearsalPlanner::class)->generate(
            CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-14')
        );
        $custom = Rehearsal::query()->whereDate('date', '2026-11-14')->firstOrFail();
        $this->travel(1)->minutes();
        $custom->update(['venue' => 'Séance déplacée manuellement']);

        // Simulate a historical rhythm edit made by the old system: no sync ran.
        $rhythm->update(['weekday' => 7, 'starts_at' => '10:00', 'venue' => 'Salle B']);
        Livewire::test(Rhythms::class)->call('repairNotebook', $rhythm->id)->assertHasNoErrors();

        $this->assertSame(EventStatus::Annulee, Rehearsal::query()
            ->whereDate('date', '2026-11-07')->firstOrFail()->status);
        $this->assertSame('Séance déplacée manuellement', $custom->fresh()->venue);
        $appointments = app(PersonalNotebook::class)->for($member)['timeline']
            ->where('kind', 'rehearsal')->pluck('at')
            ->map(fn ($date) => $date->format('Y-m-d H:i'))->all();
        $this->assertContains('2026-11-08 10:00', $appointments);
        $this->assertNotContains('2026-11-07 15:00', $appointments);
    }

    public function test_returning_to_old_weekday_recovers_only_auto_cancelled_appointments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $rhythm = $this->rhythm();
        $planner = app(RehearsalPlanner::class);
        $sync = app(RehearsalRhythmSynchronizer::class);
        $planner->generate(CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-14'));
        $saturday = Rehearsal::query()->whereDate('date', '2026-11-07')->firstOrFail();
        $originalId = $saturday->id;

        $before = $rhythm->getRawOriginal();
        $rhythm->update(['weekday' => 7, 'starts_at' => '10:00']);
        $sync->synchronize($rhythm, $before);
        $this->assertSame(EventStatus::Annulee, $saturday->fresh()->status);

        $before = $rhythm->getRawOriginal();
        $rhythm->update(['weekday' => 6, 'starts_at' => '15:00']);
        $sync->synchronize($rhythm, $before);
        $this->assertSame(EventStatus::Planifiee, $saturday->fresh()->status);
        $this->assertSame(RehearsalRhythmSynchronizer::GENERATED_NOTE, $saturday->fresh()->notes);
        $this->assertSame($originalId, Rehearsal::query()
            ->whereDate('date', '2026-11-07')->where('status', 'planifiee')->firstOrFail()->id);
        $this->assertSame(1, Rehearsal::query()->whereDate('date', '2026-11-07')->count());
    }

    public function test_manually_cancelled_rehearsals_are_never_reactivated_by_schedule_generation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $rhythm = $this->rhythm();
        $planner = app(RehearsalPlanner::class);
        $planner->generate(CarbonImmutable::parse('2026-11-07'), CarbonImmutable::parse('2026-11-07'));
        $session = Rehearsal::query()->firstOrFail();
        $session->update(['status' => EventStatus::Annulee->value]);
        $this->assertSame(0, $planner->generate(CarbonImmutable::parse('2026-11-07'), CarbonImmutable::parse('2026-11-07')));
        $this->assertSame(1, Rehearsal::query()->count());
        $this->assertSame(EventStatus::Annulee, $session->fresh()->status);
    }

    public function test_synchronizing_one_rhythm_does_not_generate_all_other_choir_rhythms(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $rhythm = $this->rhythm();
        $other = RehearsalRhythm::create([
            'choir_id' => $rhythm->choir_id,
            'weekday' => 3,
            'starts_at' => '17:00',
            'effective_from' => '2026-11-01',
            'is_active' => true,
        ]);
        $counts = app(RehearsalRhythmSynchronizer::class)->synchronize($rhythm, null);
        $this->assertGreaterThan(0, $counts['created']);
        $this->assertSame(0, Rehearsal::query()->where('rehearsal_rhythm_id', $other->id)->count());
    }

    public function test_livewire_rhythm_save_immediately_changes_notebook_schedule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $member = Member::factory()->active()->create();
        $rhythm = $this->rhythm();
        app(RehearsalPlanner::class)->generate(
            CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-14')
        );

        Livewire::test(Rhythms::class)->call('edit', $rhythm->id)
            ->set('weekday', '7')
            ->set('starts_at', '11:00')
            ->set('ends_at', '13:00')
            ->call('save')
            ->assertHasNoErrors();

        $dates = app(PersonalNotebook::class)->for($member)['timeline']
            ->where('kind', 'rehearsal')->pluck('at')
            ->map(fn ($date) => $date->format('Y-m-d H:i'))->all();
        $this->assertContains('2026-11-08 11:00', $dates);
        $this->assertNotContains('2026-11-07 15:00', $dates);
    }

    public function test_retired_rhythm_no_longer_appears_in_notebook_but_past_records_are_kept(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00:00'));
        $user = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($user);
        $member = Member::factory()->active()->create();
        $rhythm = $this->rhythm();
        app(RehearsalPlanner::class)->generate(CarbonImmutable::parse('2026-11-04'), CarbonImmutable::parse('2026-11-14'));

        $this->assertSame('archived', app(RehearsalRhythmRemoval::class)->remove($rhythm->id));
        $this->assertSame(2, Rehearsal::query()->where('rehearsal_rhythm_id', $rhythm->id)->count());
        $this->assertSame(2, Rehearsal::query()->where('rehearsal_rhythm_id', $rhythm->id)
            ->where('status', EventStatus::Annulee->value)->count());
        $this->assertSame(0, app(PersonalNotebook::class)->for($member)['timeline']
            ->where('kind', 'rehearsal')->count());
    }
}
