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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
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

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'category', 'month', 'year'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'category', 'month', 'year']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $transactionId): void
    {
        $this->authorizeManage();

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

        Transaction::findOrFail($transactionId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Opération supprimée.');
    }

    /**
     * Validation d'une écriture : elle entre alors dans les soldes.
     */
    public function validateTransaction(int $transactionId): void
    {
        $this->authorizeManage();

        $transaction = Transaction::findOrFail($transactionId);

        $transaction->update([
            'status' => TransactionStatus::Validee->value,
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Opération validée.');
    }

    /**
     * Rejet d'une écriture.
     */
    public function reject(int $transactionId): void
    {
        $this->authorizeManage();

        Transaction::findOrFail($transactionId)->update([
            'status' => TransactionStatus::Rejetee->value,
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Opération rejetée.');
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::query()
            ->with(['account', 'budget', 'member', 'activity'])
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->where('reference', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('category', 'like', $like);
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->when($this->month !== '', fn (Builder $query) => $query->whereMonth('transaction_date', (int) $this->month))
            ->when($this->year !== '', fn (Builder $query) => $query->whereYear('transaction_date', (int) $this->year))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingTransaction(): ?Transaction
    {
        return $this->editingId ? Transaction::find($this->editingId) : null;
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()->where('is_active', true)->orderBy('code')->get();
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
        $recettes = (float) Transaction::query()
            ->validated()
            ->where('type', TransactionType::Recette->value)
            ->sum('amount');

        $depenses = (float) Transaction::query()
            ->validated()
            ->where('type', TransactionType::Depense->value)
            ->sum('amount');

        return [
            'recettes' => $recettes,
            'depenses' => $depenses,
            'solde' => $recettes - $depenses,
            'brouillons' => Transaction::where('status', TransactionStatus::Brouillon->value)->count(),
            'total' => Transaction::count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.transactions.index');
    }
}
