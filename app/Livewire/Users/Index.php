<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'role', except: '')]
    public string $role = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403, "Gestion des comptes utilisateurs réservée à l'administrateur.");
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'role']);
        $this->resetPage();
    }

    public function create(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);

        $this->editingId = $userId;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $userId): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);

        if ($userId === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Vous ne pouvez pas supprimer votre propre compte.');

            return;
        }

        User::findOrFail($userId)->delete();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('member')
            ->when($this->search !== '', function (Builder $query) {
                $needle = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($needle) {
                    $q->where('name', 'like', $needle)
                        ->orWhere('username', 'like', $needle)
                        ->orWhere('email', 'like', $needle);
                });
            })
            ->when($this->role !== '', fn (Builder $query) => $query->where('role', $this->role))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingUser(): ?User
    {
        return $this->editingId ? User::find($this->editingId) : null;
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

    #[Computed]
    public function stats(): array
    {
        $rolesCommission = array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => str_starts_with($role->value, 'commission_')
        ));

        return [
            'total' => User::count(),
            'admins' => User::where('role', UserRole::Admin->value)->count(),
            'commissions' => User::whereIn('role', array_map(
                fn (UserRole $role): string => $role->value,
                $rolesCommission
            ))->count(),
            'liens' => User::whereNotNull('member_id')->count(),
            'commissions_libelles' => collect($rolesCommission)
                ->map(fn (UserRole $role): string => strtolower($role->label()))
                ->implode(', '),
        ];
    }

    public function render(): View
    {
        return view('livewire.users.index');
    }
}
