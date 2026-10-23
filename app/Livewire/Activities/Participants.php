<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityRole;
use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Activity;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
            'memberId' => 'required|exists:members,id',
            'role' => ['nullable', Rule::enum(ActivityRole::class)],
            'status' => ['required', Rule::enum(ParticipationStatus::class)],
            'hours' => 'nullable|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $memberId = (int) $validated['memberId'];

        if ($this->activity->participants()->where('members.id', $memberId)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Ce choriste participe déjà à l\'activité.');

            return;
        }

        $this->activity->participants()->attach($memberId, [
            'role' => $validated['role'] ?: null,
            'status' => $validated['status'],
            'hours' => (float) ($validated['hours'] ?: 0),
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->reset(['memberId', 'role', 'status', 'hours', 'notes']);
        $this->status = 'invite';

        $this->dispatch('toast', type: 'success', message: 'Participant ajouté.');
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
            ->where('status', 'actif')
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
