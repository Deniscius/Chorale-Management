<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityRole;
use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Activity;
use App\Models\Member;
use App\Services\ActivityParticipationFee;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Gestion des participants d'une activité (choristes, intervenants, bénévoles).
 */
class Participants extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_ACTIVITES;

    public Activity $activity;

    public ?int $memberId = null;

    /** @var array<int, string> */
    public array $selectedMemberIds = [];

    public string $memberSearch = '';

    public string $role = '';

    public string $status = 'invite';

    public string $hours = '0';

    public string $notes = '';

    public function mount(Activity $activity): void
    {
        $this->authorizeManage();

        $this->activity = $activity;
    }

    /**
     * Ajoute un participant à l'activité.
     */
    public function add(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'memberId' => ['required', Rule::exists('members', 'id')->whereNull('deleted_at')],
            'role' => ['nullable', Rule::enum(ActivityRole::class)],
            'status' => ['required', Rule::enum(ParticipationStatus::class)],
            'hours' => 'nullable|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $memberId = (int) $validated['memberId'];

        $added = DB::transaction(function () use ($memberId, $validated) {
            $activity = Activity::query()->lockForUpdate()->findOrFail($this->activity->id);
            if ($activity->participants()->where('members.id', $memberId)->exists()) {
                return false;
            }
            $activity->participants()->attach($memberId, [
                'role' => $validated['role'] ?: null,
                'status' => $validated['status'],
                'hours' => (float) ($validated['hours'] ?: 0),
                'notes' => $validated['notes'] ?: null,
            ]);
            app(ActivityParticipationFee::class)->createFor($activity, [$memberId]);

            return true;
        });
        if (! $added) {
            $this->dispatch('toast', type: 'error', message: 'Ce choriste participe déjà à l\'activité.');

            return;
        }

        $this->reset(['memberId', 'role', 'status', 'hours', 'notes']);
        $this->status = 'invite';

        $this->dispatch('toast', type: 'success', message: 'Participant ajouté.');
    }

    /** Batch enrolment only: roles, presence and hours can be edited later. */
    public function addSelected(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'selectedMemberIds' => ['required', 'array', 'min:1', 'max:500'],
            'selectedMemberIds.*' => ['required', 'integer', 'distinct'],
        ]);

        $ids = array_map('intval', $data['selectedMemberIds']);
        $eligible = Member::query()->active()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($eligible) !== count($ids)) {
            $this->addError('selectedMemberIds', 'La sélection contient un choriste indisponible.');
            return;
        }

        $newIds = DB::transaction(function () use ($eligible): array {
            $activity = Activity::query()->lockForUpdate()->findOrFail($this->activity->id);
            $existing = $activity->participants()->whereIn('members.id', $eligible)
                ->pluck('members.id')->map(fn ($id) => (int) $id)->all();
            $newIds = array_values(array_diff($eligible, $existing));
            $records = [];
            foreach ($newIds as $id) {
                $records[$id] = ['status' => ParticipationStatus::Invite->value, 'hours' => 0];
            }
            if ($records !== []) {
                $activity->participants()->attach($records);
                app(ActivityParticipationFee::class)->createFor($activity, $newIds);
            }

            return $newIds;
        }, 3);

        $this->selectedMemberIds = [];
        unset($this->availableMembers, $this->participants);
        $this->dispatch('toast', type: 'success', message: count($newIds).' participant(s) ajouté(s). Les inscriptions déjà présentes ont été conservées.');
    }

    public function selectAllVisible(): void
    {
        $this->authorizeManage();
        $this->selectedMemberIds = $this->availableMembers->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selectedMemberIds = [];
    }

    /**
     * Retrait d'un participant.
     */
    public function remove(int $memberId): void
    {
        $this->authorizeManage();

        $this->activity->participants()->detach($memberId);

        $this->dispatch('toast', type: 'success', message: 'Participant retiré.');
    }

    /**
     * Mise à jour rapide du statut de participation.
     */
    public function updateStatus(int $memberId, string $status): void
    {
        $this->authorizeManage();

        Validator::make(['status' => $status], [
            'status' => ['required', Rule::enum(ParticipationStatus::class)],
        ])->validate();

        $this->activity->participants()->updateExistingPivot($memberId, ['status' => $status]);
    }

    /**
     * Mise à jour du nombre d'heures effectuées.
     */
    public function updateHours(int $memberId, string $hours): void
    {
        $this->authorizeManage();

        Validator::make(['hours' => $hours], [
            'hours' => 'nullable|numeric|min:0|max:1000',
        ])->validate();

        $this->activity->participants()->updateExistingPivot($memberId, [
            'hours' => (float) ($hours ?: 0),
        ]);
    }

    #[Computed]
    public function participants()
    {
        return $this->activity->participants()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Choristes encore disponibles pour l'activité.
     */
    #[Computed]
    public function availableMembers()
    {
        return Member::query()
            ->active()
            ->when(trim($this->memberSearch) !== '', fn (Builder $query) => $query->search(trim($this->memberSearch)))
            ->whereDoesntHave('activityParticipants', fn (Builder $query) => $query->where('activity_id', $this->activity->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function roleOptions(): array
    {
        return ActivityRole::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ParticipationStatus::options();
    }

    #[Computed]
    public function totalHours(): float
    {
        return (float) $this->activity->participants()->sum('hours');
    }

    public function render(): View
    {
        return view('livewire.activities.participants');
    }
}
