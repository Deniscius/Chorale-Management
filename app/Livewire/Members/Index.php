<?php

namespace App\Livewire\Members;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Enums\Voice;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Choir;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_MEMBERS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'voix', except: '')]
    public string $voice = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'choir', except: '')]
    public ?int $choirId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public bool $showAccess = false;

    public ?int $accessMemberId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'voice', 'status', 'choirId'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'voice', 'status', 'choirId']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $memberId): void
    {
        $this->authorizeManage();

        $this->editingId = $memberId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    /**
     * Ouvre l'attribution du rôle d'accès (réservé à l'administrateur).
     */
    public function manageAccess(int $memberId): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);

        $this->accessMemberId = $memberId;
        $this->showAccess = true;
    }

    #[On('close-access')]
    public function closeAccess(): void
    {
        $this->showAccess = false;
        $this->accessMemberId = null;
    }

    public function delete(int $memberId): void
    {
        $this->authorizeManage();

        Member::findOrFail($memberId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Choriste supprimé.');
    }

    public function restore(int $memberId): void
    {
        $this->authorizeManage();

        Member::withTrashed()->findOrFail($memberId)->restore();
    }

    #[Computed]
    public function members()
    {
        return Member::query()
            ->with('choir')
            ->with('user')
            ->withExists(['invitation' => fn ($query) => $query->whereNull('accepted_at')->where('expires_at', '>', now())])
            ->search($this->search)
            ->when($this->voice !== '', fn ($q) => $q->where('voice', $this->voice))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->choirId, fn ($q) => $q->where('choir_id', $this->choirId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function editingMember(): ?Member
    {
        return $this->editingId ? Member::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function accessMember(): ?Member
    {
        return $this->accessMemberId ? Member::with('user')->findOrFail($this->accessMemberId) : null;
    }

    #[Computed]
    public function stats()
    {
        $row = Member::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS actif, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS invite',
            [MemberStatus::Actif->value, MemberStatus::Invite->value]
        )->toBase()->first();

        return [
            'total' => (int) $row->total,
            'actif' => (int) $row->actif,
            'invite' => (int) $row->invite,
            'par_voix' => Member::where('status', MemberStatus::Actif->value)
                ->selectRaw('voice, count(*) as total')->groupBy('voice')->pluck('total', 'voice'),
        ];
    }

    #[Computed]
    public function voiceOptions(): array
    {
        return Voice::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return MemberStatus::options();
    }

    public function render(): View
    {
        return view('livewire.members.index');
    }
}
