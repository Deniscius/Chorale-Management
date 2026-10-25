<?php

namespace App\Livewire\Commissions;

use App\Enums\Commission;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Services\CommissionResponsibilities;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class Responsibilities extends Component
{
    #[Locked]
    public int $commissionId;

    public ?string $leader_id = null;

    public ?string $associate_id = null;

    public ?string $module_key = null;

    public string $search = '';

    public function mount(int $commissionId): void
    {
        $this->authorizeAdmin();
        $this->commissionId = $commissionId;
        $entry = $this->entry;
        $this->leader_id = $entry->leader_id ? (string) $entry->leader_id : null;
        $this->associate_id = $entry->associate_id ? (string) $entry->associate_id : null;
        $this->module_key = $entry->module_key;
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
    public function entry(): ChoirCommission
    {
        return ChoirCommission::findOrFail($this->commissionId);
    }

    #[Computed]
    public function accounts()
    {
        $query = User::query()->when(trim($this->search) !== '', fn ($q) => $q->whereLike('name', '%'.mb_substr(trim($this->search), 0, 100).'%'));
        $users = $query->orderBy('name')->limit(50)->get(['id', 'name']);
        foreach (array_filter([$this->leader_id, $this->associate_id]) as $id) {
            if (! $users->contains('id', (int) $id) && ($user = User::find($id, ['id', 'name']))) {
                $users->prepend($user);
            }
        }

        return $users->unique('id');
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->validate(['leader_id' => 'nullable|integer', 'associate_id' => 'nullable|integer', 'module_key' => 'nullable|string']);
        app(CommissionResponsibilities::class)->assign($this->commissionId, $this->leader_id ? (int) $this->leader_id : null, $this->associate_id ? (int) $this->associate_id : null, $this->module_key ?: null);
        $this->dispatch('toast', type: 'success', message: 'Responsabilités et accès mis à jour.');
        $this->dispatch('close-responsibilities');
    }

    public function render()
    {
        $code = $this->entry->archive_commission ? Commission::from($this->entry->archive_commission) : null;

        return view('livewire.commissions.responsibilities', ['privateArchive' => $code?->hasPrivateArchive() ?? false]);
    }
}
