<?php

namespace App\Livewire\Contributions;

use App\Enums\ContributionStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ReceiptLedger;
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

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    #[Url(as: 'mois', except: '')]
    public string $month = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'type', except: '')]
    public string $typeCode = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['overview', 'search', 'year', 'month', 'status', 'typeCode'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'year', 'month', 'status', 'overview', 'typeCode']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $contributionId): void
    {
        $this->authorizeManage();

        $this->editingId = $contributionId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $contributionId): void
    {
        $this->authorizeManage();

        app(ReceiptLedger::class)->deleteContribution($contributionId);

        $this->dispatch('toast', type: 'success', message: 'Cotisation supprimée.');
    }

    /**
     * Enregistre le règlement d'une cotisation.
     */
    public function markAsPaid(int $contributionId): void
    {
        $this->authorizeManage();

        $contribution = Contribution::findOrFail($contributionId);

        app(ReceiptLedger::class)->saveContribution($contribution, [
            'status' => ContributionStatus::Payee->value,
            'paid_amount' => $contribution->due_amount,
            'paid_on' => today(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Cotisation marquée comme payée.');
    }

    #[Computed]
    public function contributions()
    {
        return Contribution::query()
            ->with('member')
            ->when($this->typeCode !== '', fn ($q) => $q->where('type_code', $this->typeCode))
            ->when($this->overview === 'reglees', fn ($q) => $q->where('paid_amount', '>', 0))
            ->when($this->overview === 'impayees', fn ($q) => $q->whereColumn('paid_amount', '<', 'due_amount'))
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas(
                'member',
                fn (Builder $m) => $m->whereLike('last_name', '%'.$this->search.'%')
                    ->orWhereLike('first_name', '%'.$this->search.'%')
            ))
            ->when($this->year !== '', fn (Builder $query) => $query->where('year', (int) $this->year))
            ->when($this->month !== '', fn (Builder $query) => $query->where('month', (int) $this->month))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('id')
            ->paginate(20);
    }

    #[Computed]
    public function editingContribution(): ?Contribution
    {
        return $this->editingId ? Contribution::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function members()
    {
        return Member::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ContributionStatus::options();
    }

    #[Computed]
    public function years(): array
    {
        return Contribution::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        $row = Contribution::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(due_amount), 0) AS du, COALESCE(SUM(paid_amount), 0) AS encaisse, COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) AS impayees',
            [ContributionStatus::Due->value, ContributionStatus::Retard->value]
        )->toBase()->first();
        $du = (float) $row->du;
        $encaisse = (float) $row->encaisse;

        return ['total' => (int) $row->total, 'du' => $du, 'encaisse' => $encaisse, 'reste' => $du - $encaisse, 'impayees' => (int) $row->impayees];
    }

    public function render(): View
    {
        return view('livewire.contributions.index');
    }
}
