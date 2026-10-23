<?php

namespace App\Livewire\Reminders;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Reminder;
use App\Services\ReminderDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal des rappels envoyés.
 *
 * Réservé aux responsables du module liturgique. L'écran est en lecture
 * seule : il sert à vérifier ce qui a été envoyé, à qui, par quel canal et
 * à diagnostiquer les échecs.
 */
class History extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'canal', except: '')]
    public string $channel = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'channel', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resolve(int $id, string $decision): void
    {
        $this->authorizeManage();
        app(ReminderDispatcher::class)->resolve($id, $decision);
        $this->dispatch('toast', type: 'success', message: 'Rappel rapproché.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'channel', 'status']);
        $this->resetPage();
    }

    #[Computed]
    public function reminders()
    {
        return Reminder::query()
            ->with(['rehearsal', 'member'])
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('recipient', $like)
                        ->orWhereLike('subject', $like);
                });
            })
            ->when($this->channel !== '', fn (Builder $query) => $query->where('channel', $this->channel))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest('sent_at')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function stats(): array
    {
        $counts = Reminder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'envoyes' => (int) ($counts['envoye'] ?? 0),
            'echecs' => (int) ($counts['echec'] ?? 0),
            'incertains' => (int) ($counts['incertain'] ?? 0),
            'sms' => Reminder::where('channel', ReminderChannel::Sms->value)->count(),
        ];
    }

    #[Computed]
    public function channelOptions(): array
    {
        return ReminderChannel::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ReminderStatus::options();
    }

    public function render(): View
    {
        return view('livewire.reminders.history');
    }
}
