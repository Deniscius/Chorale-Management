<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Models\Choir;
use App\Models\Rehearsal;
use App\Models\RehearsalRhythm;
use App\Services\RehearsalPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RehearsalRhythmTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_generation_is_idempotent_and_preserves_existing_sessions(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 09:00:00');
        $choir = Choir::factory()->create();
        $rhythm = RehearsalRhythm::create([
            'choir_id' => $choir->id,
            'weekday' => 6,
            'starts_at' => '15:00',
            'ends_at' => '18:00',
            'venue' => 'Salle paroissiale',
            'effective_from' => '2026-10-01',
            'is_active' => true,
        ]);
        $planner = app(RehearsalPlanner::class);
        $from = CarbonImmutable::parse('2026-10-31');
        $to = CarbonImmutable::parse('2026-11-14');

        $this->assertSame(3, $planner->generate($from, $to));
        $this->assertSame(0, $planner->generate($from, $to));
        $this->assertSame(3, Rehearsal::query()->where('rehearsal_rhythm_id', $rhythm->id)->count());
        $session = Rehearsal::query()->where('rehearsal_rhythm_id', $rhythm->id)->firstOrFail();
        $session->update(['status' => EventStatus::Annulee->value]);
        $this->assertSame(0, $planner->generate($from, $to));
        $this->assertSame(EventStatus::Annulee, $session->fresh()->status);
        CarbonImmutable::setTestNow();
    }

    public function test_special_rehearsals_remain_independent_of_regular_rhythms(): void
    {
        $choir = Choir::factory()->create();
        $special = Rehearsal::factory()->create([
            'choir_id' => $choir->id,
            'date' => '2026-11-01',
            'starts_at' => '10:00',
            'is_special' => true,
            'type' => RehearsalType::Generale->value,
        ]);
        RehearsalRhythm::create([
            'choir_id' => $choir->id, 'weekday' => 7, 'starts_at' => '15:00',
            'effective_from' => '2026-10-01', 'is_active' => true,
        ]);

        $this->assertSame(1, app(RehearsalPlanner::class)->generate(
            CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-01')
        ));
        $this->assertTrue($special->fresh()->is_special);
        $this->assertSame(2, Rehearsal::query()->whereDate('date', '2026-11-01')->count());
    }

    public function test_an_inactive_rhythm_does_not_schedule_sessions(): void
    {
        $choir = Choir::factory()->create();
        RehearsalRhythm::create([
            'choir_id' => $choir->id, 'weekday' => 6, 'starts_at' => '15:00',
            'effective_from' => '2026-10-01', 'is_active' => false,
        ]);
        $this->assertSame(0, app(RehearsalPlanner::class)->generate(
            CarbonImmutable::parse('2026-10-31'), CarbonImmutable::parse('2026-11-07')
        ));
    }
}
