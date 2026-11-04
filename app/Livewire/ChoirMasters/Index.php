<?php

namespace App\Livewire\ChoirMasters;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Services\ChoirMasterAssociates;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage-module', UserRole::MODULE_LITURGIQUE), 403);
    }

    public array $associateIds = [];

    public ?int $editingCommissionId = null;

    public string $accountSearch = '';

    public function editAssociates(int $commissionId): void
    {
        $commission = app(ChoirMasterAssociates::class)->authorizeHead($commissionId);
        $this->editingCommissionId = $commission->id;
        $this->accountSearch = '';
        $this->associateIds = $commission->choirMasters()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
    }

    public function saveAssociates(): void
    {
        abort_unless($this->editingCommissionId !== null, 422);
        $this->validate(['associateIds' => 'array|max:20', 'associateIds.*' => 'required|integer|distinct']);
        app(ChoirMasterAssociates::class)->sync($this->editingCommissionId, $this->associateIds);
        $this->editingCommissionId = null;
        $this->associateIds = [];
        $this->accountSearch = '';
        $this->dispatch('toast', type: 'success', message: 'Associés du maître de chœur mis à jour.');
    }

    public function cancelAssociates(): void
    {
        $this->editingCommissionId = null;
        $this->associateIds = [];
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('view-module', UserRole::MODULE_LITURGIQUE), 403);

        // The lead is the existing commission's designated choir master.
        // Assistants are explicit assignments, not every commission member.
        $commissions = ChoirCommission::query()
            ->where('module_key', UserRole::MODULE_LITURGIQUE)
            ->where(fn ($query) => $query->whereNull('archive_commission')
                ->orWhere('archive_commission', '!=', \App\Enums\Commission::SuivieFormation->value))
            ->whereNull('archived_at')
            ->with(['leader:id,name', 'choirMasters:id,name'])
            ->orderBy('name')
            ->get();

        $accounts = collect();
        if ($this->editingCommissionId !== null) {
            $accounts = \App\Models\User::query()
                ->when(trim($this->accountSearch) !== '', fn ($q) => $q->whereLike('name', '%'.mb_substr(trim($this->accountSearch), 0, 100).'%'))
                ->orderBy('name')->limit(60)->get(['id', 'name']);
            $selected = \App\Models\User::query()->whereIn('id', array_map('intval', $this->associateIds))->get(['id', 'name']);
            $accounts = $accounts->merge($selected)->unique('id');
        }

        return view('livewire.choir-masters.index', [
            'commissions' => $commissions,
            'availableAccounts' => $accounts,
        ]);
    }
}
