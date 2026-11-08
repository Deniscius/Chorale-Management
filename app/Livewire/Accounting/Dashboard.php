<?php

namespace App\Livewire\Accounting;

use App\Enums\ContributionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\MemberExpense;
use App\Models\Transaction;
use App\Services\ManagementYearContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Dashboard extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    public function mount(): void
    {
        $this->authorizeView();
    }

    #[Computed]
    public function period(): array
    {
        $year = app(ManagementYearContext::class)->selected();

        return [
            'label' => $year ? ucfirst($year->type).' '.$year->label : 'Toutes les périodes',
            'from' => $year?->starts_on?->toDateString(),
            'to' => $year?->ends_on?->toDateString(),
        ];
    }

    #[Computed]
    public function figures(): array
    {
        $period = $this->period;
        $transactions = Transaction::query()
            ->when($period['from'], fn ($q) => $q->whereDate('transaction_date', '>=', $period['from']))
            ->when($period['to'], fn ($q) => $q->whereDate('transaction_date', '<=', $period['to']))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?) AND type = ? THEN amount ELSE 0 END), 0) AS income, '.
                'COALESCE(SUM(CASE WHEN status IN (?, ?) AND type = ? THEN amount ELSE 0 END), 0) AS expense, '.
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS pending',
                [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value, TransactionType::Recette->value,
                    TransactionStatus::Validee->value, TransactionStatus::Cloturee->value, TransactionType::Depense->value,
                    TransactionStatus::Brouillon->value]
            )->toBase()->first();

        $contributions = Contribution::query()->accrued()
            ->when($period['from'], fn ($q) => $q->whereDate('period_starts_on', '>=', $period['from']))
            ->when($period['to'], fn ($q) => $q->whereDate('period_starts_on', '<=', $period['to']))
            ->selectRaw(
                'COALESCE(SUM(due_amount), 0) AS expected, COALESCE(SUM(paid_amount), 0) AS declared_paid, '.
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS exempt_count, '.
                'COALESCE(SUM(CASE WHEN status NOT IN (?, ?) AND due_on < ? THEN '.
                'CASE WHEN due_amount > paid_amount THEN due_amount - paid_amount ELSE 0 END ELSE 0 END), 0) AS arrears',
                [ContributionStatus::Exoneree->value, ContributionStatus::Exoneree->value,
                    ContributionStatus::Payee->value, today()->toDateString()]
            )->toBase()->first();

        // The current counter excludes older debts deliberately moved to
        // the historical counter by a tenant administrator. Their records
        // remain untouched and available for auditing.
        $arrears = Contribution::query()->accrued()
            ->when($period['from'], fn ($q) => $q->whereDate('period_starts_on', '>=', $period['from']))
            ->when($period['to'], fn ($q) => $q->whereDate('period_starts_on', '<=', $period['to']))
            ->whereNotIn('status', [ContributionStatus::Payee->value, ContributionStatus::Exoneree->value])
            ->whereDate('due_on', '<', today()->toDateString())
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('contribution_counter_resets AS reset')
                    ->whereColumn('reset.tenant_id', 'contributions.tenant_id')
                    ->whereColumn('reset.member_id', 'contributions.member_id')
                    ->where(function ($q): void {
                        $q->whereNull('reset.type_code')
                            ->orWhereColumn('reset.type_code', 'contributions.type_code');
                    })
                    ->whereColumn('reset.starts_on', '>', 'contributions.period_starts_on');
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN due_amount > paid_amount THEN due_amount - paid_amount ELSE 0 END), 0) AS total')
            ->toBase()->first();

        $donations = Donation::query()
            ->when($period['from'], fn ($q) => $q->whereDate('donated_on', '>=', $period['from']))
            ->when($period['to'], fn ($q) => $q->whereDate('donated_on', '<=', $period['to']))
            ->selectRaw('COUNT(*) AS number, COALESCE(SUM(amount), 0) AS declared')->toBase()->first();

        $income = (float) $transactions->income;
        $expense = (float) $transactions->expense;

        return [
            'income' => $income, 'expense' => $expense, 'net' => $income - $expense,
            'pending' => (int) $transactions->pending,
            'expected' => (float) $contributions->expected,
            'declared_paid' => (float) $contributions->declared_paid,
            'arrears' => (float) $arrears->total,
            'exempt_count' => (int) $contributions->exempt_count,
            'donation_count' => (int) $donations->number,
            'donation_declared' => (float) $donations->declared,
        ];
    }

    public function render(): View
    {
        return view('livewire.accounting.dashboard');
    }

    #[Computed]
    public function advances(): array
    {
        // Debts remain due when the working year changes.
        $approved = MemberExpense::query()->where('status', 'approved')->sum('amount');
        $repaid = Transaction::query()->validated()->whereHas('memberExpense', fn ($q) => $q->where('status', 'approved'))->sum('amount');

        return ['outstanding' => max(0, (float) $approved - (float) $repaid),
            'pending' => MemberExpense::query()->where('status', 'pending')->sum('amount')];
    }
}
