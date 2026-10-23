<?php

namespace App\Livewire\Instruments;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\InstrumentMaintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Maintenances extends Component
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

    public function edit(int $maintenanceId): void
    {
        $this->authorizeManage();

        $this->editingId = $maintenanceId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $maintenanceId): void
    {
        $this->authorizeManage();

        InstrumentMaintenance::findOrFail($maintenanceId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Intervention supprimée.');
    }

    #[Computed]
    public function maintenances()
    {
        return InstrumentMaintenance::query()
            ->with('instrument')
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->where('description', 'like', $like)
                        ->orWhere('provider', 'like', $like)
                        ->orWhereHas('instrument', fn (Builder $i) => $i->where('name', 'like', $like));
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingMaintenance(): ?InstrumentMaintenance
    {
        return $this->editingId ? InstrumentMaintenance::find($this->editingId) : null;
    }

    #[Computed]
    public function instruments()
    {
        return Instrument::orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MaintenanceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return MaintenanceStatus::options();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => InstrumentMaintenance::count(),
            'planifiees' => InstrumentMaintenance::whereIn('status', [
                MaintenanceStatus::Planifiee->value,
                MaintenanceStatus::EnCours->value,
            ])->count(),
            'terminees' => InstrumentMaintenance::where('status', MaintenanceStatus::Terminee->value)->count(),
            'cout' => (float) InstrumentMaintenance::sum('cost'),
        ];
    }

    public function render(): View
    {
        return view('livewire.instruments.maintenances');
    }
}
