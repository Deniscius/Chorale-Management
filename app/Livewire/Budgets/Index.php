<?php

namespace App\Livewire\Budgets;

use App\Enums\BudgetCategory;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Budget;
use App\Models\Choir;
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

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    #[Url(as: 'categorie', except: '')]
    public string $category = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'year', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'year', 'category']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $budgetId): void
    {
        $this->authorizeManage();

        $this->editingId = $budgetId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $budgetId): void
    {
        $this->authorizeManage();

        Budget::findOrFail($budgetId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Ligne budgétaire supprimée.');
    }

    #[Computed]
    public function budgets()
    {
        return Budget::query()
            ->with('choir')
            ->withPostedExpenses()
            ->withCount('transactions')
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('category', '%'.$this->search.'%'))
            ->when($this->year !== '', fn (Builder $query) => $query->where('year', (int) $this->year))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->orderByDesc('year')
            ->orderBy('category')
            ->paginate(15);
    }

    #[Computed]
    public function editingBudget(): ?Budget
    {
        return $this->editingId ? Budget::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return BudgetCategory::options();
    }

    #[Computed]
    public function years(): array
    {
        return Budget::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        $allocated = (float) Budget::sum('allocated_amount');
        $committed = (float) Budget::sum('committed_amount');
        $realized = (float) Budget::sum('opening_realized_amount') + (float) Transaction::validated()->where('type', 'depense')->whereHas('budget')->sum('amount');

        return [
            'total' => Budget::count(),
            'alloue' => $allocated,
            'engage' => $committed,
            'realise' => $realized,
            'disponible' => $allocated - $realized,
            'taux' => $allocated > 0 ? round($realized / $allocated * 100, 1) : 0.0,
        ];
    }

    public function render(): View
    {
        return view('livewire.budgets.index');
    }
}
