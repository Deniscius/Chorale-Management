<?php

namespace App\Livewire\ChoirMasters;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Services\CommissionAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Index extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view-module', UserRole::MODULE_LITURGIQUE), 403);
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('view-module', UserRole::MODULE_LITURGIQUE), 403);

        // The lead is the existing commission's designated choir master.
        // Assistants are explicit assignments, not every commission member.
        $commissions = ChoirCommission::query()
            ->where('module_key', UserRole::MODULE_LITURGIQUE)
            ->whereNull('archived_at')
            ->with(['leader:id,name', 'choirMasters:id,name'])
            ->orderBy('name')
            ->get();

        return view('livewire.choir-masters.index', ['commissions' => $commissions]);
    }
}
