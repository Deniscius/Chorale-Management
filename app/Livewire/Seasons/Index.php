<?php

namespace App\Livewire\Seasons;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\LiturgicalSeason;
use App\Models\Work;
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

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

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

    public function edit(int $seasonId): void
    {
        $this->authorizeManage();

        $this->editingId = $seasonId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $seasonId): void
    {
        $this->authorizeManage();

        LiturgicalSeason::findOrFail($seasonId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Temps liturgique supprimé.');
    }

    #[Computed]
    public function seasons()
    {
        return LiturgicalSeason::query()
            ->withCount('works')
            ->when($this->overview === 'encours', fn ($q) => $q->whereNotNull('starts_on')->whereDate('starts_on', '<=', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today())))
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('name', '%'.$this->search.'%'))
            ->orderByRaw('CASE WHEN starts_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_on')
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingSeason(): ?LiturgicalSeason
    {
        return $this->editingId ? LiturgicalSeason::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function stats(): array
    {
        $date = today()->toDateString();
        $row = LiturgicalSeason::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN starts_on IS NOT NULL AND DATE(starts_on) <= ? AND (ends_on IS NULL OR DATE(ends_on) >= ?) THEN 1 ELSE 0 END), 0) AS encours',
            [$date, $date]
        )->toBase()->first();

        return ['total' => (int) $row->total, 'oeuvres' => Work::whereNotNull('season_id')->count(), 'encours' => (int) $row->encours];
    }

    public function render(): View
    {
        return view('livewire.seasons.index');
    }
}
