<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Activity;
use App\Models\Choir;
use App\Services\ManagementYearContext;
use App\Support\SearchTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_ACTIVITES;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    #[Url(as: 'choir', except: '')]
    public ?int $choirId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public bool $showParticipants = false;

    public ?int $participantsActivityId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'period', 'choirId'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'period', 'choirId']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $activityId): void
    {
        $this->authorizeManage();

        $this->editingId = $activityId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    /**
     * Ouvre le panneau de gestion des participants d'une activité.
     */
    public function manageParticipants(int $activityId): void
    {
        $this->authorizeManage();

        $activity = Activity::query()->findOrFail($activityId);
        abort_if($activity->attendance_finalized_at !== null, 422, 'Rouvrez la fiche de présence avant de modifier les participants.');
        $this->participantsActivityId = $activityId;
        $this->showParticipants = true;
    }

    #[On('close-participants')]
    public function closeParticipants(): void
    {
        $this->showParticipants = false;
        $this->participantsActivityId = null;
    }

    public function delete(int $activityId): void
    {
        $this->authorizeManage();

        DB::transaction(function () use ($activityId): void {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activityId);
            $activity->delete();
        }, 3);

        $this->dispatch('toast', type: 'success', message: 'Activité supprimée.');
    }

    /**
     * Bascule la visibilité publique de l'activité.
     */
    public function togglePublic(int $activityId): void
    {
        $this->authorizeManage();

        $activity = Activity::findOrFail($activityId);
        $activity->update(['is_public' => ! $activity->is_public]);

        $this->dispatch('toast', type: 'success', message: $activity->is_public
            ? 'Activité publiée.'
            : 'Activité retirée de la publication.');
    }

    /**
     * Clôture de l'activité avec la rédaction du rapport.
     */
    public function close(int $activityId): void
    {
        $this->authorizeManage();

        $activity = Activity::findOrFail($activityId);
        $activity->update(['status' => ActivityStatus::Terminee->value]);

        $this->dispatch('toast', type: 'success', message: 'Activité marquée comme terminée.');
    }

    /**
     * Annulation de l'activité.
     */
    public function cancel(int $activityId): void
    {
        $this->authorizeManage();

        Activity::findOrFail($activityId)->update(['status' => ActivityStatus::Annulee->value]);

        $this->dispatch('toast', type: 'success', message: 'Activité annulée.');
    }

    #[Computed]
    public function activities()
    {
        return Activity::query()
            ->when(app(ManagementYearContext::class)->selected(), fn (Builder $query, $year) => $query->whereDate('starts_at', '>=', $year->starts_on)->whereDate('starts_at', '<=', $year->ends_on))
            ->with('commission:id,name')
            ->withCount('participants')
            ->when(trim($this->search) !== '', fn (Builder $query) => SearchTerms::apply($query, $this->search, ['title', 'description', 'venue']))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->choirId, fn (Builder $query) => $query->where('choir_id', $this->choirId))
            ->when($this->period === 'avenir', fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay()))
            ->when($this->period === 'encours', fn (Builder $query) => $query->where('starts_at', '<=', now()->endOfDay())
                ->where('ends_at', '>=', now()->startOfDay()))
            ->when($this->period === 'passees', fn (Builder $query) => $query->where('starts_at', '<', now()->startOfDay()))
            // An agenda must show the next appointment first. Completed entries
            // remain easy to find through the “Passées” filter, newest first.
            ->when($this->period === 'avenir', fn (Builder $query) => $query->orderBy('starts_at')->orderBy('id'))
            ->when($this->period === 'passees', fn (Builder $query) => $query->orderByDesc('starts_at')->orderByDesc('id'))
            ->when(! in_array($this->period, ['avenir', 'passees'], true), fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [now()->startOfDay()])
                ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [now()->startOfDay()])
                ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [now()->startOfDay()])
                ->orderBy('id'))
            ->paginate(15);
    }

    #[Computed]
    public function editingActivity(): ?Activity
    {
        return $this->editingId ? Activity::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function participantsActivity(): ?Activity
    {
        return $this->participantsActivityId ? Activity::find($this->participantsActivityId) : null;
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return ActivityType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ActivityStatus::options();
    }

    #[Computed]
    public function stats(): array
    {
        $row = Activity::query()
            ->when(app(ManagementYearContext::class)->selected(), fn (Builder $query, $year) => $query->whereDate('starts_at', '>=', $year->starts_on)->whereDate('starts_at', '<=', $year->ends_on))
            ->selectRaw(
                'COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS planifiees, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS terminees, COALESCE(SUM(CASE WHEN starts_at >= ? THEN 1 ELSE 0 END), 0) AS a_venir, COALESCE(SUM(budget_allocated), 0) AS budget, COALESCE(SUM(budget_spent), 0) AS depense',
                [ActivityStatus::Planifiee->value, ActivityStatus::Terminee->value, now()->startOfDay()]
            )->toBase()->first();

        return ['total' => (int) $row->total, 'planifiees' => (int) $row->planifiees, 'terminees' => (int) $row->terminees, 'a_venir' => (int) $row->a_venir, 'budget' => (float) $row->budget, 'depense' => (float) $row->depense];
    }

    public function render(): View
    {
        return view('livewire.activities.index');
    }
}
