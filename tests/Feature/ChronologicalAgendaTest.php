<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Form as ActivityForm;
use App\Livewire\Activities\Index as ActivitiesIndex;
use App\Livewire\Performances\Form as PerformanceForm;
use App\Livewire\Performances\Index as PerformancesIndex;
use App\Livewire\Rehearsals\Form as RehearsalForm;
use App\Models\Activity;
use App\Models\Performance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ChronologicalAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-06 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_activities_show_the_next_dates_first_in_chronological_order(): void
    {
        $user = User::factory()->role(UserRole::CommissionVieCulturelle)->create();
        Activity::factory()->create(['title' => 'Passée récente', 'starts_at' => now()->subDay()]);
        Activity::factory()->create(['title' => 'Dans trois jours', 'starts_at' => now()->addDays(3)]);
        Activity::factory()->create(['title' => 'Demain', 'starts_at' => now()->addDay()]);

        Livewire::actingAs($user)->test(ActivitiesIndex::class)
            ->assertSeeInOrder(['Demain', 'Dans trois jours', 'Passée récente']);
    }

    public function test_performances_show_the_next_dates_first_in_chronological_order(): void
    {
        $user = User::factory()->role(UserRole::CommissionLiturgique)->create();
        Performance::factory()->create(['title' => 'Concert passé', 'scheduled_at' => now()->subDay()]);
        Performance::factory()->create(['title' => 'Messe dans trois jours', 'scheduled_at' => now()->addDays(3)]);
        Performance::factory()->create(['title' => 'Messe demain', 'scheduled_at' => now()->addDay()]);

        Livewire::actingAs($user)->test(PerformancesIndex::class)
            ->assertSeeInOrder(['Messe demain', 'Messe dans trois jours', 'Concert passé']);
    }

    public function test_date_suggestions_keep_a_native_date_picker_and_fill_the_correct_value(): void
    {
        $activityUser = User::factory()->role(UserRole::CommissionVieCulturelle)->create();
        Livewire::actingAs($activityUser)->test(ActivityForm::class)
            ->call('useSuggestedDate', 'next_week')
            ->assertSet('starts_at', '2026-11-13T19:00')
            ->assertSet('ends_at', '2026-11-13T21:00')
            ->assertSee('type="datetime-local"', false);

        $liturgicalUser = User::factory()->role(UserRole::CommissionLiturgique)->create();
        Livewire::actingAs($liturgicalUser)->test(PerformanceForm::class)
            ->call('useSuggestedDate', 'tomorrow')
            ->assertSet('scheduled_at', '2026-11-07T19:00')
            ->assertSee('type="datetime-local"', false);

        Livewire::actingAs($liturgicalUser)->test(RehearsalForm::class)
            ->call('useSuggestedDate', 'next_week')
            ->assertSet('date', '2026-11-13')
            ->assertSee('type="date"', false);
    }
}
