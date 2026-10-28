<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Form;
use App\Models\Activity;
use App\Models\ChoirCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ActivityCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_is_organized_by_a_commission_instead_of_an_individual(): void
    {
        $manager = User::factory()->role(UserRole::CommissionVieCulturelle)->create();
        $commission = ChoirCommission::create([
            'name' => 'Vie culturelle',
            'name_key' => 'vie culturelle',
            'kind' => 'permanent',
            'mission' => 'Organiser les activités de la chorale.',
            'starts_on' => today(),
            'module_key' => 'activites',
        ]);

        Livewire::actingAs($manager)->test(Form::class)
            ->assertSet('choir_commission_id', (string) $commission->id)
            ->set('title', 'Sortie fraternelle')
            ->set('type', 'sortie')
            ->set('starts_at', now()->addWeek()->format('Y-m-d H:i'))
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::firstOrFail();
        $this->assertSame($commission->id, $activity->choir_commission_id);
        $this->assertNull($activity->organizer_id);
        $this->actingAs($manager)->get(route('activities.index'))->assertOk()->assertSee('Vie culturelle');
    }
}
