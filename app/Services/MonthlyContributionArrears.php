<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MonthlyContributionArrears
{
    public function __construct(private readonly FinancialCatalog $catalog) {}

    /**
     * Calcule la situation mensuelle sans créer d'écriture comptable.
     *
     * Un mois déjà enregistré conserve son montant dû historique. Un mois
     * absent utilise le montant mensuel actuellement configuré par la chorale.
     * Sans date d'adhésion, aucun mois manquant n'est inventé.
     *
     * @return array{from:?CarbonImmutable, through:CarbonImmutable, expected_months:int, recorded_months:int, missing_months:int, default_amount:float, outstanding:float, missing_amount:float}
     */
    public function for(Member $member): array
    {
        $through = CarbonImmutable::today()->startOfMonth();
        if ($member->left_on && $member->left_on->lt($through)) {
            $through = CarbonImmutable::instance($member->left_on)->startOfMonth();
        }

        $monthly = $member->contributions()
            ->where('type_code', 'mensuelle')
            ->where(fn ($query) => $query->where('year', '<', $through->year)
                ->orWhere(fn ($current) => $current->where('year', $through->year)->where('month', '<=', $through->month)))
            ->get();

        $defaultAmount = (float) ($this->catalog->all()->firstWhere('code', 'mensuelle')?->default_amount ?? 0);
        $from = $member->joined_on
            ? CarbonImmutable::instance($member->joined_on)->startOfMonth()
            : null;

        if ($from === null || $from->gt($through)) {
            return $this->fromRecordedOnly($monthly, $through, $defaultAmount);
        }

        $byPeriod = $monthly->keyBy(fn ($item) => sprintf('%04d-%02d', $item->year, $item->month));
        $expected = 0;
        $recorded = 0;
        $missing = 0;
        $outstanding = 0.0;
        $cursor = $from;

        while ($cursor->lte($through)) {
            $expected++;
            $entry = $byPeriod->get($cursor->format('Y-m'));
            if ($entry) {
                $recorded++;
                $outstanding += max(0, (float) $entry->due_amount - (float) $entry->paid_amount);
            } else {
                $missing++;
                $outstanding += $defaultAmount;
            }
            $cursor = $cursor->addMonth();
        }

        return [
            'from' => $from,
            'through' => $through,
            'expected_months' => $expected,
            'recorded_months' => $recorded,
            'missing_months' => $missing,
            'default_amount' => $defaultAmount,
            'missing_amount' => $missing * $defaultAmount,
            'outstanding' => $outstanding,
        ];
    }

    /** @param Collection<int, Contribution> $monthly */
    private function fromRecordedOnly(Collection $monthly, CarbonImmutable $through, float $defaultAmount): array
    {
        return [
            'from' => null,
            'through' => $through,
            'expected_months' => $monthly->count(),
            'recorded_months' => $monthly->count(),
            'missing_months' => 0,
            'default_amount' => $defaultAmount,
            'missing_amount' => 0.0,
            'outstanding' => (float) $monthly->sum(fn ($item) => max(0, (float) $item->due_amount - (float) $item->paid_amount)),
        ];
    }
}
