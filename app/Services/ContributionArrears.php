<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\ContributionCounterReset;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ContributionArrears
{
    /** 
     * A historical cut-off changes only the displayed current arrears.
     * Older debts and payment/ledger records remain intact.
     */
    public function startNewCounter(Member $member, CarbonImmutable $date, string $reason, ?string $typeCode = null): ContributionCounterReset
    {
        Gate::authorize('manage-module', \App\Enums\UserRole::MODULE_COMPTABILITE);
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Un motif de cinq caractères minimum est obligatoire.']);
        }

        return DB::transaction(fn () => ContributionCounterReset::create([
            'member_id' => $member->id,
            'type_code' => $typeCode,
            'starts_on' => $date->toDateString(),
            'reason' => trim($reason),
            'approved_by' => auth()->id(),
        ]));
    }

    /** Current arrears for one member, after the latest applicable cut-off. */
    public function currentArrears(Member $member, ?string $typeCode = null): float
    {
        $reset = ContributionCounterReset::query()->where('member_id', $member->id)
            ->where(function ($q) use ($typeCode): void {
                $q->whereNull('type_code');
                if ($typeCode !== null) {
                    $q->orWhere('type_code', $typeCode);
                }
            })
            ->orderByDesc('starts_on')->orderByDesc('id')->first();

        return (float) Contribution::query()
            ->where('member_id', $member->id)
            ->when($typeCode !== null, fn ($q) => $q->where('type_code', $typeCode))
            ->when($reset, fn ($q) => $q->whereDate('period_starts_on', '>=', $reset->starts_on))
            ->whereNotIn('status', [ContributionStatus::Exoneree->value, ContributionStatus::Payee->value])
            ->whereDate('due_on', '<', today())
            ->selectRaw('COALESCE(SUM(GREATEST(due_amount - paid_amount, 0)), 0) AS arrears')
            ->value('arrears');
    }
}
