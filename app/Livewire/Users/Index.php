<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use App\Services\AccountWriter;
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

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

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
        if (in_array($property, ['overview', 'search', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'role', 'overview']);
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

        app(AccountWriter::class)->delete(User::findOrFail($userId));
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('member')
            ->when($this->overview === 'commissions', fn ($q) => $q->whereLike('role', 'commission_%'))
            ->when($this->overview === 'lies', fn ($q) => $q->whereNotNull('member_id'))
            ->when($this->search !== '', function (Builder $query) {
                $needle = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($needle) {
                    $q->whereLike('name', $needle)
                        ->orWhereLike('username', $needle)
                        ->orWhereLike('email', $needle);
                });
            })
            ->when($this->role !== '', fn (Builder $query) => $query->where('role', $this->role))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function editingUser(): ?User
    {
        return $this->editingId ? User::findOrFail($this->editingId) : null;
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
        $roles = array_map(fn (UserRole $role): string => $role->value, $rolesCommission);
        $placeholders = implode(', ', array_fill(0, count($roles), '?'));
        $row = User::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN role = ? THEN 1 ELSE 0 END), 0) AS admins, COALESCE(SUM(CASE WHEN role IN ('.$placeholders.') THEN 1 ELSE 0 END), 0) AS commissions, COALESCE(SUM(CASE WHEN member_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS liens',
            [UserRole::Admin->value, ...$roles]
        )->toBase()->first();

        return [
            'total' => (int) $row->total,
            'admins' => (int) $row->admins,
            'commissions' => (int) $row->commissions,
            'liens' => (int) $row->liens,
            'commissions_libelles' => collect($rolesCommission)->map(fn (UserRole $role): string => strtolower($role->label()))->implode(', '),
        ];
    }

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    public function render(): View
    {
        return view('livewire.users.index');
    }
}
