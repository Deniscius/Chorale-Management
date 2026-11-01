<?php

namespace App\Livewire\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'action', except: '')]
    public string $action = '';

    #[Url(as: 'utilisateur', except: '')]
    public string $actorId = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'du', except: '')]
    public string $from = '';

    #[Url(as: 'au', except: '')]
    public string $until = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'actorId', 'search', 'from', 'until'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('action', 'actorId', 'search', 'from', 'until');
        $this->resetPage();
    }

    private function filteredEvents(): Builder
    {
        $this->validate([
            'action' => ['nullable', 'in:created,updated,deleted,login,logout'],
            'actorId' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return AuditEvent::query()
            ->when($this->action !== '', fn (Builder $q) => $q->where('action', $this->action))
            ->when($this->actorId !== '', fn (Builder $q) => $q->where('actor_id', (int) $this->actorId))
            ->when($this->from !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->until !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $this->until))
            ->when(trim($this->search) !== '', function (Builder $q): void {
                $term = trim($this->search);
                $q->where(fn (Builder $query) => $query
                    ->whereHas('actor', fn (Builder $users) => $users->whereLike('name', '%'.$term.'%')
                        ->orWhereLike('username', '%'.$term.'%'))
                    ->when(ctype_digit($term), fn (Builder $numbers) => $numbers->orWhere('subject_id', (int) $term)));
            });
    }

    #[Computed]
    public function events()
    {
        return $this->filteredEvents()->with('actor:id,name')->latest('id')->paginate(25);
    }

    #[Computed]
    public function users()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function recentLogins()
    {
        return AuditEvent::query()->where('action', 'login')
            ->with('actor:id,name')->latest('id')->limit(8)->get();
    }

    public function render(): View
    {
        return view('livewire.audit.index');
    }
}
