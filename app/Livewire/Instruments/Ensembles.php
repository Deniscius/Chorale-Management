<?php

namespace App\Livewire\Instruments;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\InstrumentEnsemble;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Ensembles extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $ensembleId): void
    {
        $this->authorizeManage();

        $this->editingId = $ensembleId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $ensembleId): void
    {
        $this->authorizeManage();

        InstrumentEnsemble::findOrFail($ensembleId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Ensemble supprimé.');
    }

    #[Computed]
    public function ensembles()
    {
        return InstrumentEnsemble::query()
            ->with('conductor')
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('name', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingEnsemble(): ?InstrumentEnsemble
    {
        return $this->editingId ? InstrumentEnsemble::find($this->editingId) : null;
    }

    #[Computed]
    public function conductors()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => InstrumentEnsemble::count(),
            'effectif' => (int) InstrumentEnsemble::sum('member_count'),
            'moyenne' => InstrumentEnsemble::count() > 0
                ? (int) round(InstrumentEnsemble::avg('member_count'))
                : 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.instruments.ensembles');
    }
}
