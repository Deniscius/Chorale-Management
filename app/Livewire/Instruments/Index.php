<?php

namespace App\Livewire\Instruments;

use App\Enums\InstrumentCategory;
use App\Enums\InstrumentCondition;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\Member;
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

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'categorie', except: '')]
    public string $category = '';

    #[Url(as: 'etat', except: '')]
    public string $condition = '';

    #[Url(as: 'sortie', except: '')]
    public string $borrowed = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'condition', 'borrowed'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'condition', 'borrowed']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $instrumentId): void
    {
        $this->authorizeManage();

        $this->editingId = $instrumentId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $instrumentId): void
    {
        $this->authorizeManage();

        Instrument::findOrFail($instrumentId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Instrument retiré de l\'inventaire.');
    }

    #[Computed]
    public function instruments()
    {
        return Instrument::query()
            ->with('custodian')
            ->withCount('movements')
            ->withSum(['movements as loaned_quantity' => fn ($q) => $q->whereIn('type', ['sortie', 'pret'])->whereNull('actual_return_on')], 'quantity')
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('name', $like)
                        ->orWhereLike('serial_number', $like)
                        ->orWhereLike('location', $like);
                });
            })
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->when($this->condition !== '', fn (Builder $query) => $query->where('condition', $this->condition))
            ->when($this->borrowed === 'oui', fn (Builder $query) => $query->where('is_borrowed', true))
            ->when($this->borrowed === 'non', fn (Builder $query) => $query->where('is_borrowed', false))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingInstrument(): ?Instrument
    {
        return $this->editingId ? Instrument::find($this->editingId) : null;
    }

    #[Computed]
    public function custodians()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return InstrumentCategory::options();
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return InstrumentCondition::options();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Instrument::count(),
            'quantite' => (int) Instrument::sum('quantity'),
            'valeur' => (float) Instrument::sum('current_value'),
            'horsservice' => Instrument::where('condition', InstrumentCondition::HorsService->value)->count(),
            'sortis' => Instrument::where('is_borrowed', true)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.instruments.index');
    }
}
