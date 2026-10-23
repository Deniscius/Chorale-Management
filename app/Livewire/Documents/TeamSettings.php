<?php

namespace App\Livewire\Documents;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\CommissionTeam;
use App\Models\User;
use App\Services\CommissionTeamWriter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class TeamSettings extends Component
{
    #[Locked]
    public string $commission;

    public ?string $leader_id = null;

    public ?string $associate_id = null;

    public string $search = '';

    public function mount(string $commission): void
    {
        $this->commission = $commission;
        $team = $this->team();
        $this->authorizeTeam($team);
        $this->leader_id = $team->leader_id ? (string) $team->leader_id : null;
        $this->associate_id = $team->associate_id ? (string) $team->associate_id : null;
    }

    private function team(): CommissionTeam
    {
        return CommissionTeam::where('commission', $this->commission)->firstOrFail();
    }

    private function authorizeTeam(CommissionTeam $team): void
    {
        abort_unless(auth()->user()?->isAdmin() || ($team->leader_id === auth()->id() && $team->allows(auth()->user())), 403);
    }

    public function hydrate(): void
    {
        $this->authorizeTeam($this->team());
    }

    public function save(): void
    {
        $this->authorizeTeam($this->team());
        $this->validate(['leader_id' => 'required|integer', 'associate_id' => 'nullable|integer']);
        app(CommissionTeamWriter::class)->save(Commission::from($this->commission), (int) $this->leader_id, $this->associate_id ? (int) $this->associate_id : null);
        $this->dispatch('toast', type: 'success', message: 'Responsable et associé mis à jour.');
        $this->dispatch('close-team');
    }

    #[Computed]
    public function leaders()
    {
        $role = $this->commission === Commission::Liturgique->value ? UserRole::CommissionLiturgique : UserRole::Secretaire;

        return User::where('role', $role)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function associates()
    {
        $users = User::query()->when(trim($this->search) !== '', fn ($q) => $q->whereLike('name', '%'.mb_substr(trim($this->search), 0, 100).'%'))->orderBy('name')->limit(30)->get(['id', 'name']);
        if ($this->associate_id && ! $users->contains('id', (int) $this->associate_id)) {
            $selected = User::find($this->associate_id, ['id', 'name']);
            if ($selected) {
                $users->prepend($selected);
            }
        }

        return $users;
    }

    public function render()
    {
        return view('livewire.documents.team-settings');
    }
}
