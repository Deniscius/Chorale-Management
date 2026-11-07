<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Choir;
use App\Models\Rehearsal;
use App\Models\RehearsalRhythm;
use App\Models\User;
use App\Services\RehearsalPlanner;
use App\Services\RehearsalRhythmRemoval;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

final class RehearsalRhythmRetirementTest extends TestCase
{
    use RefreshDatabase;

    private function rhythm(): RehearsalRhythm
    {
        return RehearsalRhythm::create([
            'choir_id' => Choir::factory()->create()->id,
            'weekday' => 6,
            'starts_at' => '15:00',
            'ends_at' => '18:00',
            'effective_from' => '2026-11-01',
            'is_active' => true,
        ]);
    }

    public function test_unused_rhythm_is_physically_removed(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $rhythm = $this->rhythm();

        $this->assertSame('deleted', app(RehearsalRhythmRemoval::class)->remove($rhythm->id));
        $this->assertNull(RehearsalRhythm::find($rhythm->id));
    }

    public function test_used_rhythm_is_deactivated_without_deleting_rehearsals(): void
    {
        CarbonImmutable::setTestNow('2026-11-06 09:00:00');
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $rhythm = $this->rhythm();
        $from = CarbonImmutable::parse('2026-11-07');

        $this->assertSame(1, app(RehearsalPlanner::class)->generate($from, $from));
        $this->assertSame('archived', app(RehearsalRhythmRemoval::class)->remove($rhythm->id));
        $this->assertFalse($rhythm->fresh()->is_active);
        $this->assertSame(1, Rehearsal::query()->where('rehearsal_rhythm_id', $rhythm->id)->count());
        $this->assertSame(0, app(RehearsalPlanner::class)->generate($from->addWeek(), $from->addWeek()));
        CarbonImmutable::setTestNow();
    }

    public function test_ordinary_chorister_cannot_remove_a_rhythm(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Choriste)->create());
        $rhythm = $this->rhythm();
        $this->expectException(AuthorizationException::class);
        app(RehearsalRhythmRemoval::class)->remove($rhythm->id);
    }
}
