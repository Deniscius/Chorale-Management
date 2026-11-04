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

    public function editAssociates(int $commissionId): void
    {
        $commission = app(ChoirMasterAssociates::class)->authorizeHead($commissionId);
        $this->editingCommissionId = $commission->id;
        $this->associateIds = $commission->choirMasters()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
    }

    public function saveAssociates(): void
    {
        abort_unless($this->editingCommissionId !== null, 422);
        $this->validate(['associateIds' => 'array|max:20', 'associateIds.*' => 'required|integer|distinct']);
        app(ChoirMasterAssociates::class)->sync($this->editingCommissionId, $this->associateIds);
        $this->editingCommissionId = null;
        $this->associateIds = [];
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

        return view('livewire.choir-masters.index', [
            'commissions' => $commissions,
            'availableAccounts' => $this->editingCommissionId !== null
                ? \App\Models\User::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }
}
