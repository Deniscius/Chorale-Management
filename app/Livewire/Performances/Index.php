<?php

namespace App\Livewire\Performances;

use App\Enums\EventStatus;
use App\Enums\PerformanceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Performance;
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

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'period']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $performanceId): void
    {
        $this->authorizeManage();

        $this->editingId = $performanceId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $performanceId): void
    {
        $this->authorizeManage();

        Performance::findOrFail($performanceId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Célébration supprimée.');
    }

    #[Computed]
    public function performances()
    {
        return Performance::query()
            ->with('works')
            ->withSum(['transactions as spent_amount' => fn (Builder $query) => $query
                ->where('type', 'depense')->whereIn('status', ['validee', 'cloturee'])], 'amount')
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('title', $like)
                        ->orWhereLike('venue', $like)
                        ->orWhereLike('audience', $like);
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->period === 'avenir', fn (Builder $query) => $query->where('scheduled_at', '>=', now()))
            ->when($this->period === 'passee', fn (Builder $query) => $query->where('scheduled_at', '<', now()))
            ->orderBy('scheduled_at')
            ->paginate(15);
    }

    #[Computed]
    public function editingPerformance(): ?Performance
    {
        return $this->editingId ? Performance::with('works')->findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function typeOptions(): array
    {
        return PerformanceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return EventStatus::options();
    }

    #[Computed]
    public function stats(): array
    {
        $row = Performance::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN scheduled_at >= ? THEN 1 ELSE 0 END), 0) AS avenir, COALESCE(SUM(budget), 0) AS budget, COALESCE(SUM(revenue), 0) AS recettes', [now()]
        )->toBase()->first();

        return ['total' => (int) $row->total, 'avenir' => (int) $row->avenir, 'budget' => (float) $row->budget, 'recettes' => (float) $row->recettes];
    }

    public function render(): View
    {
        return view('livewire.performances.index');
    }
}
