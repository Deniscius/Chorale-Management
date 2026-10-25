<?php

namespace App\Livewire\Members;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use App\Services\AccountWriter;
use App\Services\MemberOnboarding;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Access extends Component
{
    public Member $member;

    public string $role = 'choriste';

    #[Locked]
    public ?string $activationLink = null;

    public function mount(Member $member): void
    {
        $this->authorizeManage();
        $this->member = $member;
        $this->role = $member->user?->role->value ?? $this->invitation?->role->value ?? UserRole::Choriste->value;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->validate(['role' => ['required', Rule::enum(UserRole::class)]]);
        $user = $this->member->user()->first();
        if ($user) {
            app(AccountWriter::class)->save($user, ['role' => $this->role]);
            $this->dispatch('toast', type: 'success', message: 'Rôle mis à jour. Les informations de connexion restent personnelles.');
            $this->dispatch('close-access');
        } else {
            $this->activationLink = app(MemberOnboarding::class)->issue($this->member, UserRole::from($this->role));
            unset($this->invitation);
            $this->dispatch('toast', type: 'success', message: 'Accès préparé. Transmettez le lien personnel au membre.');
        }
    }

    public function revoke(): void
    {
        $this->authorizeManage();
        if ($this->member->user()->whereKey(auth()->id())->exists()) {
            $this->addError('role', 'Vous ne pouvez pas retirer votre propre accès.');

            return;
        }
        app(MemberOnboarding::class)->revoke($this->member);
        $this->activationLink = null;
        $this->dispatch('toast', type: 'success', message: 'Accès et lien d’activation retirés.');
        $this->dispatch('close-access');
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    public function hydrate(): void
    {
        $this->authorizeManage();
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRole::options();
    }

    #[Computed]
    public function existingAccount(): ?User
    {
        return $this->member->user;
    }

    #[Computed]
    public function invitation(): ?MemberInvitation
    {
        return MemberInvitation::where('member_id', $this->member->id)->first();
    }

    #[Computed]
    public function suggestedUsername(): string
    {
        return app(MemberOnboarding::class)->suggestUsername($this->member);
    }

    public function render()
    {
        return view('livewire.members.access');
    }
}
