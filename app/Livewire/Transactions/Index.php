<?php

namespace App\Livewire\Transactions;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\ManagementYearContext;
use App\Services\TransactionWriter;
use App\Support\SearchTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'categorie', except: '')]
    public string $category = '';

    #[Url(as: 'mois', except: '')]
    public string $month = '';

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    #[Url(as: 'activite')]
    public ?int $activityId = null;

    #[Url(as: 'compte')]
    public ?int $accountId = null;

    #[Locked]
    public ?int $classificationId = null;

    public bool $showClassification = false;

    public string $classificationActivity = '';

    public string $classificationAccount = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['overview', 'search', 'type', 'status', 'category', 'month', 'year', 'activityId', 'accountId'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'category', 'month', 'year', 'overview', 'activityId', 'accountId']);
        $this->resetPage();
    }

    #[Computed]
    public function selectedActivity(): ?Activity
    {
        return $this->activityId ? Activity::query()->find($this->activityId) : null;
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function classify(int $transactionId): void
    {
        $this->authorizeManage();
        $entry = Transaction::query()->findOrFail($transactionId);
        abort_unless($entry->status === TransactionStatus::Brouillon, 422, 'Cette écriture n’est plus en brouillon.');
        $this->resetValidation();
        $this->classificationId = $entry->id;
        $this->classificationActivity = (string) ($entry->activity_id ?? '');
        $this->classificationAccount = (string) ($entry->account_id ?? '');
        $this->showClassification = true;
    }

    public function saveClassification(): void
    {
        $this->authorizeManage();
        $this->validate(['classificationActivity' => 'nullable|integer', 'classificationAccount' => 'nullable|integer']);
        abort_unless($this->classificationId, 422);
        app(TransactionWriter::class)->assignContext($this->classificationId,
            $this->classificationActivity !== '' ? (int) $this->classificationActivity : null,
            $this->classificationAccount !== '' ? (int) $this->classificationAccount : null);
        $this->closeClassification();
        $this->dispatch('toast', type: 'success', message: 'Rattachement comptable enregistré. Le montant et le règlement d’origine sont conservés.');
    }

    #[On('close-classification')]
    public function closeClassification(): void
    {
        $this->reset('classificationId', 'classificationActivity', 'classificationAccount', 'showClassification');
    }

    public function edit(int $transactionId): void
    {
        $this->authorizeManage();

        $transaction = Transaction::findOrFail($transactionId);
        if ($transaction->isLocked() || $transaction->isReceipt()) {
            $this->dispatch('toast', type: 'error', message: 'Opération protégée : utilisez le règlement d’origine ou une écriture de régularisation.');

            return;
        }
        $this->editingId = $transactionId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $transactionId): void
    {
        $this->authorizeManage();

        app(TransactionWriter::class)->delete($transactionId);
        $this->dispatch('toast', type: 'success', message: 'Opération supprimée.');
    }

    /**
     * Validation d'une écriture : elle entre alors dans les soldes.
     */
    public function validateTransaction(int $transactionId): void
    {
        $this->authorizeManage();

        app(TransactionWriter::class)->transition($transactionId, TransactionStatus::Validee);
        $this->dispatch('toast', type: 'success', message: 'Opération validée.');
    }

    /**
     * Rejet d'une écriture.
     */
    public function reject(int $transactionId): void
    {
        $this->authorizeManage();

        app(TransactionWriter::class)->transition($transactionId, TransactionStatus::Rejetee);
        $this->dispatch('toast', type: 'success', message: 'Opération rejetée.');
    }

    #[Computed]
    public function transactions()
    {
        $transactions = $this->filteredQuery()->with(['budget', 'member', 'performance'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15);
        // The filter lists already contain these records; reuse them for each row.
        $accounts = $this->accounts->keyBy('id');
        $activities = $this->activities->keyBy('id');
        foreach ($transactions as $transaction) {
            $transaction->setRelation('account', $accounts->get($transaction->account_id));
            $transaction->setRelation('activity', $activities->get($transaction->activity_id));
        }

        return $transactions;
    }

    private function filteredQuery(): Builder
    {
        return Transaction::query()
            ->when($this->activityId, fn (Builder $query) => $query->where('activity_id', $this->activityId))
            ->when($this->accountId, fn (Builder $query) => $query->where('account_id', $this->accountId))
            ->when(! $this->activityId ? app(ManagementYearContext::class)->selected() : null, fn (Builder $query, $year) => $query->whereDate('transaction_date', '>=', $year->starts_on)->whereDate('transaction_date', '<=', $year->ends_on))
            ->when($this->overview === 'validees', fn ($q) => $q->validated())
            ->when(trim($this->search) !== '', fn (Builder $query) => SearchTerms::apply($query, $this->search, ['reference', 'description', 'category']))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->when($this->month !== '', fn (Builder $query) => $query->whereMonth('transaction_date', (int) $this->month))
            ->when($this->year !== '', fn (Builder $query) => $query->whereYear('transaction_date', (int) $this->year));
    }

    #[Computed]
    public function editingTransaction(): ?Transaction
    {
        return $this->editingId ? Transaction::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()->orderBy('code')->get();
    }

    #[Computed]
    public function budgets()
    {
        return Budget::query()->orderByDesc('year')->orderBy('category')->get();
    }

    #[Computed]
    public function members()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function activities()
    {
        return Activity::query()->orderByDesc('starts_at')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return TransactionType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return TransactionStatus::options();
    }

    #[Computed]
    public function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    /**
     * Postes de dépenses / recettes proposés à la saisie.
     */
    #[Computed]
    public function categoryOptions(): array
    {
        $categories = Transaction::query()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return array_combine($categories, $categories) ?: [];
    }

    #[Computed]
    public function stats(): array
    {
        $row = $this->filteredQuery()
            ->selectRaw(
                'COUNT(*) AS total, COALESCE(SUM(CASE WHEN status IN (?, ?) AND type = ? THEN amount ELSE 0 END), 0) AS recettes, COALESCE(SUM(CASE WHEN status IN (?, ?) AND type = ? THEN amount ELSE 0 END), 0) AS depenses, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS brouillons',
                [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value, TransactionType::Recette->value, TransactionStatus::Validee->value, TransactionStatus::Cloturee->value, TransactionType::Depense->value, TransactionStatus::Brouillon->value]
            )->toBase()->first();
        $recettes = (float) $row->recettes;
        $depenses = (float) $row->depenses;

        return ['recettes' => $recettes, 'depenses' => $depenses, 'solde' => $recettes - $depenses, 'brouillons' => (int) $row->brouillons, 'total' => (int) $row->total];
    }

    public function render(): View
    {
        return view('livewire.transactions.index');
    }
}
