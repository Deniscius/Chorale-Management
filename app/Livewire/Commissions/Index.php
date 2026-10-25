<?php

namespace App\Livewire\Commissions;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Services\ChoirCommissionWriter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_MEMBERS;

    public string $search = '';

    public string $status = '';

    public string $kindFilter = '';

    public bool $mine = false;

    #[Locked]
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $viewingId = null;

    #[Locked]
    public ?int $responsibilityId = null;

    public string $name = '';

    public string $mission = '';

    public string $kind = 'permanent';

    public string $starts_on = '';

    public ?string $ends_on = null;

    public array $member_ids = [];

    public string $memberSearch = '';

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function hydrate(): void
    {
        $this->authorizeView();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'kindFilter', 'mine'], true)) {
            $this->resetPage();
        }
    }

    public function filterStatus(string $status): void
    {
        abort_unless($status === '' || array_key_exists($status, ChoirCommission::statuses()), 422);
        $this->status = $status;
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('manage-commissions');
        $this->reset('editingId', 'name', 'mission', 'kind', 'ends_on', 'member_ids', 'memberSearch');
        $this->starts_on = today()->toDateString();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-commissions');
        $entry = ChoirCommission::with('members:id')->findOrFail($id);
        $this->editingId = $id;
        $this->fill($entry->only('name', 'mission', 'kind'));
        $this->starts_on = $entry->starts_on->toDateString();
        $this->ends_on = $entry->ends_on?->toDateString();
        $this->member_ids = $entry->members->modelKeys();
        $this->memberSearch = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function view(int $id): void
    {
        ChoirCommission::findOrFail($id);
        $this->viewingId = $id;
    }

    public function delegate(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        ChoirCommission::findOrFail($id);
        $this->showForm = false;
        $this->viewingId = null;
        $this->responsibilityId = $id;
    }

    #[On('close-responsibilities')]
    public function closeResponsibilities(): void
    {
        $this->responsibilityId = null;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->viewingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        app(ChoirCommissionWriter::class)->save($this->editingId, $this->only('name', 'mission', 'kind', 'starts_on', 'ends_on', 'member_ids'));
        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: 'Commission enregistrée.');
    }

    public function toggleArchive(int $id): void
    {
        app(ChoirCommissionWriter::class)->toggleArchive($id);
        $this->dispatch('toast', type: 'success', message: 'Statut de la commission mis à jour.');
    }

    public function render()
    {
        $query = ChoirCommission::query()->with('leader:id,name')->withCount('members');
        if (trim($this->search) !== '') {
            $search = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$search])->orWhereRaw('LOWER(mission) LIKE ?', [$search]));
        }
        if (in_array($this->kindFilter, ['permanent', 'ad_hoc'], true)) {
            $query->where('kind', $this->kindFilter);
        }
        if ($this->mine) {
            $memberId = auth()->user()->member_id;
            $query->where(fn ($q) => $q->where('leader_id', auth()->id())->orWhereHas('members', fn ($q) => $q->where('members.id', $memberId ?? 0))->orWhere(fn ($q) => $q->where('module_key', 'instruments')->whereHas('instrumentAssociates', fn ($q) => $q->where('users.id', auth()->id()))));
        }
        $counts = collect(ChoirCommission::statuses())->map(fn ($label, $key) => ChoirCommission::inStatus($key)->count())->all();
        // The roster is loaded only while editing; cards use counts instead of N+1 queries.
        $members = $this->showForm ? Member::orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'status']) : collect();

        return view('livewire.commissions.index', [
            'commissions' => $query->inStatus($this->status)->orderBy('name')->paginate(12),
            'counts' => $counts,
            'members' => $members,
            'detail' => $this->viewingId ? ChoirCommission::with(['members' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')])->findOrFail($this->viewingId) : null,
        ]);
    }
}
