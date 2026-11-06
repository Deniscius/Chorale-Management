<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommissionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_associate_can_open_only_own_commission_report(): void
    {
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $own = ChoirCommission::create(['name' => 'Activités', 'name_key' => 'activites', 'mission' => 'Organisation', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => 'activites', 'leader_id' => User::factory()->create()->id, 'associate_id' => $associate->id]);
        $own->members()->attach(Member::factory()->create()->id);
        $other = ChoirCommission::create(['name' => 'Finances', 'name_key' => 'finances', 'mission' => 'Comptabilité', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => 'comptabilite']);

        $this->actingAs($associate)->get(route('reports.commission', $own))->assertOk()->assertSee('Rapport de commission')->assertSee('Activités');
        $this->actingAs($associate)->get(route('reports.commission', $other))->assertForbidden();
    }
}
