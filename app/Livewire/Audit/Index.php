<?php

namespace App\Livewire\Audit;

use App\Models\AuditEvent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $action = '';

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    #[Computed]
    public function events()
    {
        return AuditEvent::with('actor:id,name')->when($this->action !== '', fn ($q) => $q->where('action', $this->action))->latest('id')->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.audit.index');
    }
}
