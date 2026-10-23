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
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderByRaw('CASE WHEN starts_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_on')
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingSeason(): ?LiturgicalSeason
    {
        return $this->editingId ? LiturgicalSeason::find($this->editingId) : null;
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => LiturgicalSeason::count(),
            'oeuvres' => Work::whereNotNull('season_id')->count(),
            'encours' => LiturgicalSeason::whereNotNull('starts_on')
                ->whereDate('starts_on', '<=', today())
                ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.seasons.index');
    }
}
