<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public ?User $user = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:50|regex:/^[a-z0-9][a-z0-9._-]*$/')]
    public string $username = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $role = 'choriste';

    #[Validate]
    public ?int $member_id = null;

    #[Validate('nullable|min:8|confirmed')]
    public ?string $password = null;

    #[Validate('nullable')]
    public ?string $password_confirmation = null;

    protected function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'member_id' => ['nullable', 'exists:members,id', Rule::unique('users', 'member_id')->ignore($this->user)],
        ];
    }

    public function mount(?User $user = null): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403, "Gestion des comptes utilisateurs réservée à l'administrateur.");

        $user = $user?->exists ? $user : null;
        $this->user = $user;

        if ($user) {
            $this->fill([
                'name' => $user->name,
                'username' => $user->username ?? '',
                'email' => $user->email,
                'role' => $user->role->value,
                'member_id' => $user->member_id,
            ]);
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);

        $this->username = strtolower(trim($this->username));

        $this->validate();

        // L'identifiant doit rester unique : on exclut le compte en cours d'édition.
        $duplicate = User::query()
            ->where('username', $this->username)
            ->when($this->user, fn ($query) => $query->whereKeyNot($this->user->id))
            ->exists();

        if ($duplicate) {
            $this->addError('username', 'Cet identifiant est déjà utilisé par un autre compte.');

            return;
        }

        $data = [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'member_id' => $this->member_id,
        ];

        if (filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->user) {
            $this->user = app(AccountWriter::class)->save($this->user, $data);
            $message = 'Compte utilisateur mis à jour.';
        } else {
            if (blank($this->password)) {
                $this->addError('password', 'Un mot de passe est obligatoire pour la création du compte.');

                return;
            }

            $data['password'] = $this->password;
            $this->user = app(AccountWriter::class)->save(null, $data);
            $message = 'Compte utilisateur créé.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
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
    public function members()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function roleOptions(): array
    {
        return UserRole::options();
    }

    /**
     * À la création, propose un identifiant à partir de l'e-mail saisi.
     */
    public function updatedEmail(string $value): void
    {
        if (! $this->user && blank($this->username)) {
            $this->username = User::suggestUsername($value);
        }
    }

    /**
     * Rattachement possible à une fiche choriste.
     */
    #[Computed]
    public function availableMembers()
    {
        $used = User::whereNotNull('member_id')->pluck('member_id')->all();

        return $this->members->reject(fn (Member $member) => in_array($member->id, $used, true) && $member->id !== $this->member_id);
    }

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    public function render(): View
    {
        return view('livewire.users.form');
    }
}
