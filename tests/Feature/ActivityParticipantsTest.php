<?php

namespace Tests\Feature;

use App\Enums\ActivityRole;
use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Livewire\Activities\Participants;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vérifie la gestion des participants d'une activité.
 */
class ActivityParticipantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_responsable_ajoute_un_participant(): void
    {
        $activity = Activity::factory()->create();
        $member = Member::factory()->create();

        $admin = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($admin)
            ->test(Participants::class, ['activity' => $activity])
            ->set('memberId', $member->id)
            ->set('role', ActivityRole::Soliste->value)
            ->set('status', ParticipationStatus::Present->value)
            ->set('hours', '3.5')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activity_participants', [
            'activity_id' => $activity->id,
            'member_id' => $member->id,
            'role' => ActivityRole::Soliste->value,
            'status' => ParticipationStatus::Present->value,
            'hours' => 3.5,
        ]);
    }

    public function test_un_participant_ne_peut_pas_etre_ajoute_deux_fois(): void
    {
        $activity = Activity::factory()->create();
        $member = Member::factory()->create();

        $activity->participants()->attach($member->id, [
            'status' => ParticipationStatus::Invite->value,
            'hours' => 0,
        ]);

        $admin = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($admin)
            ->test(Participants::class, ['activity' => $activity])
            ->set('memberId', $member->id)
            ->call('add');

        $this->assertSame(1, $activity->participants()->count());
    }

    public function test_un_responsable_retire_un_participant(): void
    {
        $activity = Activity::factory()->create();
        $member = Member::factory()->create();

        $activity->participants()->attach($member->id, [
            'status' => ParticipationStatus::Invite->value,
            'hours' => 0,
        ]);

        $admin = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($admin)
            ->test(Participants::class, ['activity' => $activity])
            ->call('remove', $member->id);

        $this->assertDatabaseMissing('activity_participants', [
            'activity_id' => $activity->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_le_total_des_heures_est_calcule(): void
    {
        $activity = Activity::factory()->create();
        $premier = Member::factory()->create();
        $second = Member::factory()->create();

        $activity->participants()->attach($premier->id, ['status' => ParticipationStatus::Present->value, 'hours' => 2.5]);
        $activity->participants()->attach($second->id, ['status' => ParticipationStatus::Present->value, 'hours' => 4]);

        $admin = User::factory()->role(UserRole::Admin)->create();

        $total = Livewire::actingAs($admin)
            ->test(Participants::class, ['activity' => $activity])
            ->instance()
            ->totalHours();

        $this->assertSame(6.5, $total);
    }

    public function test_un_choriste_ne_peut_pas_gerer_les_participants(): void
    {
        $this->assertFalse(
            User::factory()->role(UserRole::Choriste)->create()
                ->can('manage-module', UserRole::MODULE_ACTIVITES)
        );
    }
}
