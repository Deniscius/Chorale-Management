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

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

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
        if (in_array($property, ['overview', 'search', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'overview']);
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
            ->when($this->overview === 'a_faire', fn ($q) => $q->whereIn('status', ['planifiee', 'en_cours']))
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('description', $like)
                        ->orWhereLike('provider', $like)
                        ->orWhereHas('instrument', fn (Builder $i) => $i->whereLike('name', $like));
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
        return $this->editingId ? InstrumentMaintenance::findOrFail($this->editingId) : null;
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
        $row = InstrumentMaintenance::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) AS planifiees, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS terminees, COALESCE(SUM(cost), 0) AS cout',
            [MaintenanceStatus::Planifiee->value, MaintenanceStatus::EnCours->value, MaintenanceStatus::Terminee->value]
        )->toBase()->first();

        return ['total' => (int) $row->total, 'planifiees' => (int) $row->planifiees, 'terminees' => (int) $row->terminees, 'cout' => (float) $row->cout];
    }

    public function render(): View
    {
        return view('livewire.instruments.maintenances');
    }
}
