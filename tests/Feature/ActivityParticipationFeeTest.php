<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contribution;
use App\Models\Member;
use App\Livewire\Activities\Form;
use App\Services\ActivityParticipationFee;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ActivityParticipationFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_activity_participant_receives_one_traceable_contribution(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale participation', 'name' => 'Responsable',
            'username' => 'participation-admin', 'email' => 'participation@example.test', 'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function (): void {
            $first = Member::factory()->active()->create();
            $second = Member::factory()->active()->create();
            $activity = Activity::factory()->create([
                'title' => 'Retraite annuelle', 'starts_at' => '2026-11-20 08:00:00',
                'participation_fee_enabled' => true, 'participation_fee_amount' => 3500,
                'participation_fee_due_on' => '2026-11-15',
            ]);
            $activity->participants()->attach($first->id, ['status' => 'invite', 'hours' => 0]);

            $service = app(ActivityParticipationFee::class);
            $this->assertSame(1, $service->syncExisting($activity));
            $this->assertSame(0, $service->syncExisting($activity));

            $activity->participants()->attach($second->id, ['status' => 'invite', 'hours' => 0]);
            $this->assertSame(1, $service->createFor($activity, [$second->id]));

            $entries = Contribution::query()->with('activity')->where('activity_id', $activity->id)->orderBy('member_id')->get();
            $this->assertCount(2, $entries);
            $this->assertSame('participation-activite-'.$activity->id, $entries->first()->type_code);
            $this->assertSame('3500.00', $entries->first()->due_amount);
            $this->assertSame('2026-11-15', $entries->first()->due_on->toDateString());
            $this->assertSame($activity->id, $entries->first()->activity->id);
        });
    }

    public function test_activity_form_can_toggle_the_participation_fee_section(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale formulaire', 'name' => 'Responsable',
            'username' => 'formulaire-admin', 'email' => 'formulaire@example.test', 'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            Livewire::actingAs($admin)->test(Form::class)
                ->set('participation_fee_enabled', true)
                ->assertSet('participation_fee_enabled', true)
                ->assertSee('Montant demandé');
        });
    }
}
