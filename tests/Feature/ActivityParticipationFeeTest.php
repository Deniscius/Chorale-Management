<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contribution;
use App\Models\ChoirCommission;
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

    public function test_existing_activity_with_a_fee_can_be_rendered_and_updated(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale modification', 'name' => 'Responsable',
            'username' => 'modification-admin', 'email' => 'modification@example.test', 'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $activity = Activity::factory()->create([
                'participation_fee_enabled' => true,
                'participation_fee_amount' => 3000,
                'participation_fee_due_on' => null,
            ]);

            Livewire::actingAs($admin)->test(Form::class, ['activity' => $activity])
                ->assertSet('participation_fee_enabled', true)
                ->assertSet('participation_fee_amount', '3000.00')
                ->set('participation_fee_enabled', false)
                ->assertSet('participation_fee_enabled', false)
                ->assertSee('x-show="enabled"', false)
                ->assertDontSee('wire:model.live="participation_fee_enabled"', false);
        });
    }

    public function test_existing_programmed_activity_can_be_made_chargeable(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale activation', 'name' => 'Responsable',
            'username' => 'activation-admin', 'email' => 'activation@example.test', 'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $member = Member::factory()->active()->create();
            $commission = ChoirCommission::create([
                'name' => 'Secrétariat', 'name_key' => 'secretariat activation', 'kind' => 'permanent',
                'mission' => 'Organiser les activités.', 'starts_on' => today(), 'module_key' => 'activites',
            ]);
            $startsAt = now()->addWeek()->startOfHour();
            $activity = Activity::factory()->create([
                'title' => 'Sortie déjà programmée', 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(2),
                'choir_commission_id' => $commission->id,
                'participation_fee_enabled' => false,
            ]);
            $activity->participants()->attach($member->id, ['status' => 'invite', 'hours' => 0]);

            Livewire::actingAs($admin)->test(Form::class, ['activity' => $activity])
                ->set('participation_fee_enabled', true)
                ->set('participation_fee_amount', '3000')
                ->set('participation_fee_due_on', now()->addDays(3)->toDateString())
                ->call('save')
                ->assertHasNoErrors()
                ->assertDispatched('close-form');

            $this->assertTrue($activity->fresh()->participation_fee_enabled);
            $this->assertDatabaseHas('contributions', [
                'tenant_id' => $admin->tenant_id, 'activity_id' => $activity->id,
                'member_id' => $member->id, 'due_amount' => 3000,
            ]);
        });
    }
}
