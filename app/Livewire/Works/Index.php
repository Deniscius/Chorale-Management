<?php

namespace App\Livewire\Works;

use App\Enums\Difficulty;
use App\Enums\UserRole;
use App\Enums\WorkType;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Document;
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

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'saison', except: '')]
    public ?int $seasonId = null;

    #[Url(as: 'niveau', except: '')]
    public string $difficulty = '';

    #[Url(as: 'repertoire', except: '')]
    public string $repertoire = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['overview', 'search', 'type', 'seasonId', 'difficulty', 'repertoire'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'seasonId', 'difficulty', 'repertoire', 'overview']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $workId): void
    {
        $this->authorizeManage();

        $this->editingId = $workId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $workId): void
    {
        $this->authorizeManage();

        Work::findOrFail($workId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Œuvre supprimée.');
    }

    public function restore(int $workId): void
    {
        $this->authorizeManage();

        Work::withTrashed()->findOrFail($workId)->restore();
    }

    public function toggleRepertoire(int $workId): void
    {
        $this->authorizeManage();

        $work = Work::findOrFail($workId);
        $work->update(['is_repertoire' => ! $work->is_repertoire]);
    }

    #[Computed]
    public function works()
    {
        return Work::query()
            ->with('season')
            ->when($this->overview === 'saisons', fn ($q) => $q->whereNotNull('season_id'))
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('title', $like)
                        ->orWhereLike('composer', $like)
                        ->orWhereLike('arranger', $like)
                        ->orWhereLike('text_author', $like);
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->seasonId, fn (Builder $query) => $query->where('season_id', $this->seasonId))
            ->when($this->difficulty !== '', fn (Builder $query) => $query->where('difficulty', $this->difficulty))
            ->when($this->repertoire === 'oui', fn (Builder $query) => $query->where('is_repertoire', true))
            ->when($this->repertoire === 'non', fn (Builder $query) => $query->where('is_repertoire', false))
            ->orderBy('title')
            ->paginate(15);
    }

    #[Computed]
    public function editingWork(): ?Work
    {
        return $this->editingId ? Work::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function seasons()
    {
        return LiturgicalSeason::orderBy('starts_on')->orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return WorkType::options();
    }

    #[Computed]
    public function difficultyOptions(): array
    {
        return Difficulty::options();
    }

    #[Computed]
    public function stats(): array
    {
        $row = Work::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_repertoire = ? THEN 1 ELSE 0 END), 0) AS repertoire, COALESCE(SUM(duration_minutes), 0) AS duree',
            [true]
        )->toBase()->first();

        return [
            'total' => (int) $row->total,
            'repertoire' => (int) $row->repertoire,
            'partitions' => Document::query()->readable(auth()->user())->where('commission', 'commission_liturgique')->where('category', 'Partition')->count(),
            'duree' => (int) $row->duree,
        ];
    }

    public function render(): View
    {
        return view('livewire.works.index');
    }
}
