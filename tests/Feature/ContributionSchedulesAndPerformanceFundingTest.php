<?php

namespace Tests\Feature;

use App\Livewire\Performances\Form as PerformanceForm;
use App\Livewire\Settings\FinancialTypes;
use App\Models\Contribution;
use App\Models\FinancialType;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ContributionSchedule;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContributionSchedulesAndPerformanceFundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return app(TenantRegistration::class)->create([
            'organization' => 'Chorale Test', 'name' => 'Responsable', 'username' => 'schedule-admin',
            'email' => 'schedule@example.test', 'password' => 'SecretChorale2026',
        ]);
    }

    public function test_due_day_controls_generation_and_sync_is_idempotent(): void
    {
        $admin = $this->admin();
        app(TenantContext::class)->run($admin->tenant_id, function (): void {
            $member = Member::factory()->active()->create(['joined_on' => '2026-01-10']);
            $type = FinancialType::where('code', 'mensuelle')->firstOrFail();
            $type->update(['default_amount' => 2000, 'frequency_months' => 1, 'starts_on' => '2026-02-01', 'due_day' => 20]);
            Contribution::factory()->create([
                'member_id' => $member->id, 'type_code' => 'mensuelle', 'year' => 2026, 'month' => 3,
                'due_amount' => 3500, 'paid_amount' => 0, 'status' => 'due',
            ]);

            $this->assertSame(7, app(ContributionSchedule::class)->sync());
            $this->assertSame(0, app(ContributionSchedule::class)->sync());
            $this->assertSame(8, Contribution::where('member_id', $member->id)->count());
            $this->assertFalse(Contribution::where('year', 2026)->where('month', 10)->exists());
            $this->assertSame('3500.00', Contribution::where('month', 3)->value('due_amount'));
            $this->assertSame('2026-02-20', Contribution::where('month', 2)->value('due_on')->format('Y-m-d'));
        });
    }

    public function test_quarterly_schedule_uses_the_next_period_after_membership_and_clamps_month_end(): void
    {
        $admin = $this->admin();
        app(TenantContext::class)->run($admin->tenant_id, function (): void {
            $member = Member::factory()->active()->create(['joined_on' => '2026-02-01']);
            $type = FinancialType::where('code', 'mensuelle')->firstOrFail();
            $type->update(['frequency_months' => 3, 'starts_on' => '2026-01-01', 'due_day' => 31]);
            $periods = app(ContributionSchedule::class)->duePeriods($member, $type, CarbonImmutable::parse('2026-10-31'));

            $this->assertSame(['2026-04-30', '2026-07-31', '2026-10-31'], $periods->pluck('due_on')->map->toDateString()->all());
        });
    }

    public function test_admin_configures_mass_support_in_a_modal_and_it_prefills_new_events(): void
    {
        $admin = $this->admin();
        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            Livewire::actingAs($admin)->test(FinancialTypes::class)
                ->call('openFunding')->assertSet('showFundingModal', true)
                ->set('performance_budgets.messe', '25000')->call('saveFunding')->assertHasNoErrors();

            $this->assertSame('25000', Setting::get('finance.performance_budget.messe'));
            Livewire::actingAs($admin)->test(PerformanceForm::class)->assertSet('budget', '25000');
        });
    }

    public function test_validated_expense_can_be_traced_to_its_celebration(): void
    {
        $admin = $this->admin();
        app(TenantContext::class)->run($admin->tenant_id, function (): void {
            $performance = Performance::factory()->create(['type' => 'messe', 'budget' => 30000]);
            Transaction::factory()->depense()->create(['performance_id' => $performance->id, 'amount' => 12000]);

            $this->assertSame(12000.0, (float) $performance->transactions()->validated()->sum('amount'));
        });
    }
}
