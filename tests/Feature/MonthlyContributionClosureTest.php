<?php

namespace Tests\Feature;

use App\Models\FinancialType;
use App\Models\Member;
use App\Services\ContributionSchedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class MonthlyContributionClosureTest extends TestCase
{
    public function test_november_is_not_due_on_november_first(): void
    {
        $member = new Member(['joined_on' => '2026-09-01']);
        $type = new FinancialType(['frequency_months' => 1, 'due_day' => 1]);

        $periods = app(ContributionSchedule::class)->duePeriods(
            $member, $type, CarbonImmutable::parse('2026-11-01')
        );

        $this->assertSame(['2026-09-01', '2026-10-01'], $periods
            ->pluck('period')->map(fn ($date) => $date->toDateString())->all());
        $this->assertSame(['2026-09-30', '2026-10-31'], $periods
            ->pluck('due_on')->map(fn ($date) => $date->toDateString())->all());
    }

    public function test_november_is_not_due_even_on_its_last_calendar_day(): void
    {
        $member = new Member(['joined_on' => '2026-11-01']);
        $type = new FinancialType(['frequency_months' => 1, 'due_day' => 1]);

        $this->assertCount(0, app(ContributionSchedule::class)->duePeriods(
            $member, $type, CarbonImmutable::parse('2026-11-30')
        ));
        $periods = app(ContributionSchedule::class)->duePeriods(
            $member, $type, CarbonImmutable::parse('2026-12-01')
        );
        $this->assertCount(1, $periods);
        $this->assertSame('2026-11-01', $periods->first()['period']->toDateString());
    }

    public function test_non_monthly_frequency_keeps_its_configured_due_day(): void
    {
        $member = new Member(['joined_on' => '2026-09-01']);
        $type = new FinancialType(['frequency_months' => 3, 'due_day' => 10]);

        $periods = app(ContributionSchedule::class)->duePeriods(
            $member, $type, CarbonImmutable::parse('2026-11-01')
        );

        $this->assertCount(1, $periods);
        $this->assertSame('2026-09-10', $periods->first()['due_on']->toDateString());
    }
}
