<?php

namespace App\Livewire\Users;

use App\Models\ChoirCommission;
use App\Models\User;
use App\Services\UserCommissionAssignments;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class CommissionAssignments extends Component
{
    #[Locked]
    public int $userId;

    #[Locked]
    public string $fingerprint;

    public bool $saved = false;

    public array $members = [];

    public array $leaders = [];

    public array $instrumentAssociates = [];

    public array $archiveAssociates = [];

    public function mount(User $user): void
    {
        $this->authorizeAdmin();
        $this->userId = $user->id;
        $this->fingerprint = app(UserCommissionAssignments::class)->fingerprint($user);
        foreach ($this->commissions as $entry) {
            if ($user->member_id && $entry->members->contains('id', $user->member_id)) {
                $this->members[] = $entry->id;
            }
            if ((int) $entry->leader_id === $user->id) {
                $this->leaders[] = $entry->id;
            }
            if ($entry->instrumentAssociates->contains('id', $user->id)) {
                $this->instrumentAssociates[] = $entry->id;
            }
            if ((int) $entry->associate_id === $user->id) {
                $this->archiveAssociates[] = $entry->id;
            }
        }
    }

    public function hydrate(): void
    {
        $this->authorizeAdmin();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function account(): User
    {
        return User::with('member')->findOrFail($this->userId);
    }

    #[Computed]
    public function commissions()
    {
        return ChoirCommission::with(['leader:id,name', 'associate:id,name', 'members' => fn ($q) => $q->select('members.id'), 'instrumentAssociates' => fn ($q) => $q->select('users.id')])->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        app(UserCommissionAssignments::class)->save($this->userId, $this->members, $this->leaders, $this->instrumentAssociates, $this->archiveAssociates, $this->fingerprint);
        $this->fingerprint = app(UserCommissionAssignments::class)->fingerprint($this->account);
        $this->members = array_values(array_unique([...$this->members, ...$this->leaders, ...$this->instrumentAssociates, ...$this->archiveAssociates]));
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.users.commission-assignments');
    }
}
