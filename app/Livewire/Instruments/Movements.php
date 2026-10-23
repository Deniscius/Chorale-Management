<?php

namespace App\Livewire\Instruments;

use App\Enums\InstrumentCondition;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Services\InstrumentLoans;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Movements extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $movementId): void
    {
        $this->authorizeManage();

        $this->editingId = $movementId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $movementId): void
    {
        $this->authorizeManage();

        InstrumentMovement::findOrFail($movementId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Mouvement supprimé.');
    }

    /**
     * Clôture rapide d'un mouvement : l'instrument est rendu.
     */
    public function close(int $movementId): void
    {
        $this->authorizeManage();

        app(InstrumentLoans::class)->close($movementId);

        $this->dispatch('toast', type: 'success', message: 'Mouvement clôturé.');
    }

    #[Computed]
    public function movements()
    {
        return InstrumentMovement::query()
            ->with(['instrument', 'responsible'])
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('destination', $like)
                        ->orWhereLike('reason', $like)
                        ->orWhereHas('instrument', fn (Builder $i) => $i->whereLike('name', $like));
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status === 'en_cours', fn (Builder $query) => $query->whereNull('actual_return_on'))
            ->when($this->status === 'cloture', fn (Builder $query) => $query->whereNotNull('actual_return_on'))
            ->when($this->status === 'en_retard', fn (Builder $query) => $query->whereNull('actual_return_on')
                ->whereNotNull('expected_return_on')
                ->whereDate('expected_return_on', '<', today()))
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingMovement(): ?InstrumentMovement
    {
        return $this->editingId ? InstrumentMovement::find($this->editingId) : null;
    }

    #[Computed]
    public function instruments()
    {
        return Instrument::orderBy('name')->get();
    }

    #[Computed]
    public function responsibles()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MovementType::options();
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return InstrumentCondition::options();
    }

    #[Computed]
    public function stats(): array
    {
        $enCours = InstrumentMovement::whereNull('actual_return_on')->count();
        $enRetard = InstrumentMovement::whereNull('actual_return_on')
            ->whereNotNull('expected_return_on')
            ->whereDate('expected_return_on', '<', today())
            ->count();

        return [
            'total' => InstrumentMovement::count(),
            'en_cours' => $enCours,
            'en_retard' => $enRetard,
            'clotures' => InstrumentMovement::whereNotNull('actual_return_on')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.instruments.movements');
    }
}
