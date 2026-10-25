<?php

namespace App\Livewire\Members;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Attribution du rôle d'accès d'un choriste.
 *
 * Chaque choriste peut disposer d'un compte applicatif dont le rôle est
 * attribué par l'administrateur. L'identifiant de connexion est dérivé du
 * nom du choriste puis ajusté pour rester unique.
 */
class Access extends Component
{
    public Member $member;

    #[Validate('required')]
    public string $role = 'choriste';

    #[Validate('required|string|max:50|regex:/^[a-z0-9][a-z0-9._-]*$/')]
    public string $username = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|min:8')]
    public ?string $password = null;

    public function mount(Member $member): void
    {
        $this->authorizeManage();

        $this->member = $member;
        $this->member->loadMissing('user');
        $user = $this->member->user;

        $this->role = $user?->role->value ?? UserRole::Choriste->value;
        $this->username = $user?->username ?? $this->suggestUsername();
        $this->email = $user?->email ?? (string) $member->email;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->username = strtolower(trim($this->username));
        $this->email = mb_strtolower(trim($this->email));

        // Règles déclarées sur les propriétés (identifiant, e-mail, mot de passe).
        $this->validate(array_merge($this->getRules(), [
            'role' => ['required', Rule::enum(UserRole::class)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('users', 'username')->ignore($this->member->user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->member->user)],
        ]));

        $this->member->loadMissing('user');
        $user = $this->member->user;

        if (! $user) {
            // Le cast « hashed » du modèle hache lui-même la valeur.
            $user = new User([
                'password' => $this->password ?: Str::password(16),
                'email_verified_at' => now(),
            ]);
            $user->member_id = $this->member->id;
        } elseif (filled($this->password)) {
            $user->password = $this->password;
        }

        $user->name = $this->member->full_name;
        $user->username = $this->username;
        $user->email = $this->email;
        $user->role = $this->role;
        $user->member_id = $this->member->id;
        $user = app(AccountWriter::class)->save($user->exists ? $user : null, $user->getAttributes());

        $this->reset('password');
        $this->member->refresh();

        $this->dispatch('toast', type: 'success', message: 'Rôle du choriste mis à jour.');
        $this->dispatch('close-access');
    }

    /**
     * Retire le compte d'accès du choriste.
     */
    public function revoke(): void
    {
        $this->authorizeManage();

        $this->member->loadMissing('user');
        $user = $this->member->user;

        if ($user && $user->id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Vous ne pouvez pas retirer votre propre accès.');

            return;
        }

        if ($user) {
            app(AccountWriter::class)->delete($user);
        }

        $this->dispatch('toast', type: 'success', message: 'Accès retiré.');
        $this->dispatch('close-access');
    }

    protected function suggestUsername(): string
    {
        $base = Str::slug($this->member->full_name, '.');
        $base = $base !== '' ? substr($base, 0, 40) : 'choriste'.$this->member->id;

        $candidate = $base;
        while (Validator::make(['username' => $candidate], ['username' => 'unique:users,username'])->fails()) {
            $candidate = $base.'.'.Str::lower(Str::random(6));
        }

        return $candidate;
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403, "Attribution des rôles réservée à l'administrateur.");
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => "L'identifiant ne peut contenir que des lettres minuscules, des chiffres, des points, des tirets et des tirets bas.",
        ];
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRole::options();
    }

    #[Computed]
    public function existingAccount(): ?User
    {
        $this->member->loadMissing('user');

        return $this->member->user;
    }

    public function render(): View
    {
        return view('livewire.members.access');
    }
}
