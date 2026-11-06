<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Index as ActivitiesIndex;
use App\Livewire\Commissions\Responsibilities;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\User;
use App\Services\CommissionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CommissionAssociateCrudAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_associate_has_same_module_crud_as_commission_leader(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create(['member_id' => Member::factory()->create()->id]);
        $associate = User::factory()->role(UserRole::Choriste)->create(['member_id' => Member::factory()->create()->id]);
        $commission = ChoirCommission::create(['name' => 'Équipe activités', 'name_key' => 'equipe activites', 'mission' => 'Organiser les activités.', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => UserRole::MODULE_ACTIVITES]);

        Livewire::actingAs($admin)->test(Responsibilities::class, ['commissionId' => $commission->id])
            ->set('leader_id', (string) $leader->id)->set('associate_id', (string) $associate->id)->call('save')->assertHasNoErrors();

        app(CommissionAccess::class)->forget();
        $this->assertTrue($associate->fresh()->canManageModule(UserRole::MODULE_ACTIVITES));
        $this->assertTrue($commission->fresh()->members()->whereKey($associate->member_id)->exists());
        Livewire::actingAs($associate)->test(ActivitiesIndex::class)->call('create')->assertSet('showForm', true);
    }

    public function test_associate_cannot_exist_without_a_commission_leader(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $commission = ChoirCommission::create(['name' => 'Équipe activités', 'name_key' => 'equipe activites', 'mission' => 'Organiser les activités.', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => UserRole::MODULE_ACTIVITES]);

        Livewire::actingAs($admin)->test(Responsibilities::class, ['commissionId' => $commission->id])
            ->set('associate_id', (string) $associate->id)->call('save')->assertHasErrors('leader_id');
    }
}
