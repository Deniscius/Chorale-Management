<?php

namespace Tests\Feature;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\FinancialType;
use App\Models\Member;
use App\Models\User;
use App\Services\ContributionExemptionManager;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionExemptionManagerTest extends TestCase
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
            'organization' => 'Chorale Exonérations',
            'name' => 'Trésorier',
            'username' => 'exemption-admin',
            'email' => 'exemption@example.test',
            'password' => 'SecretChorale2026',
        ]);
    }

    public function test_reset_covers_manual_arrears_without_due_date_but_never_paid_contributions(): void
    {
        $admin = $this->admin();

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $member = Member::factory()->active()->create(['joined_on' => '2026-01-01']);
            FinancialType::where('code', 'mensuelle')->firstOrFail()->update(['default_amount' => 2000]);

            $arrear = Contribution::factory()->create([
                'member_id' => $member->id,
                'type_code' => 'mensuelle',
                'year' => 2026,
                'month' => 2,
                'period_starts_on' => null,
                'due_on' => null,
                'due_amount' => 2000,
                'paid_amount' => 0,
                'status' => ContributionStatus::Retard,
            ]);
            $alreadyPaid = Contribution::factory()->create([
                'member_id' => $member->id,
                'type_code' => 'mensuelle',
                'year' => 2026,
                'month' => 3,
                'period_starts_on' => null,
                'due_on' => null,
                'due_amount' => 2000,
                'paid_amount' => 500,
                'status' => ContributionStatus::Due,
            ]);
            $coveredCurrentPeriod = Contribution::factory()->create([
                'member_id' => $member->id,
                'type_code' => 'mensuelle',
                'year' => 2026,
                'month' => 10,
                'period_starts_on' => null,
                'due_on' => null,
                'due_amount' => 2000,
                'paid_amount' => 0,
                'status' => ContributionStatus::Due,
            ]);

            [$exemption, $updated] = app(ContributionExemptionManager::class)->create([
                'member_id' => $member->id,
                'type_code' => 'mensuelle',
                'starts_on' => '2026-09-01',
                'ends_on' => null,
                'clears_prior_arrears' => true,
                'reason' => 'Décision exceptionnelle',
                'approved_by' => $admin->id,
            ]);

            $this->assertSame(2, $updated);
            $this->assertSame(0.0, (float) $arrear->refresh()->due_amount);
            $this->assertSame(ContributionStatus::Exoneree, $arrear->status);
            $this->assertSame($exemption->id, $arrear->exemption_id);
            $this->assertSame(0.0, (float) $coveredCurrentPeriod->refresh()->due_amount);
            $this->assertSame(500.0, (float) $alreadyPaid->refresh()->paid_amount);
            $this->assertSame(2000.0, (float) $alreadyPaid->due_amount);
            $this->assertNotSame(ContributionStatus::Exoneree, $alreadyPaid->status);
        });
    }
}
