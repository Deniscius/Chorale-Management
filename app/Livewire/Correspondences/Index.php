<?php

namespace App\Livewire\Correspondences;

use App\Enums\CorrespondenceChannel;
use App\Enums\CorrespondenceStatus;
use App\Enums\CorrespondenceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Correspondence;
use App\Models\CorrespondenceEvent;
use App\Models\CorrespondenceService;
use App\Models\Member;
use App\Support\CalendarYears;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_COURRIERS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'canal', except: '')]
    public string $channel = '';

    #[Url(as: 'service', except: '')]
    public ?int $serviceId = null;

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public bool $showServiceForm = false;

    public string $serviceName = '';

    public string $serviceDescription = '';

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['overview', 'search', 'type', 'status', 'channel', 'serviceId', 'year'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'channel', 'serviceId', 'year', 'overview']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $correspondenceId): void
    {
        $this->authorizeManage();

        $this->editingId = $correspondenceId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $correspondenceId): void
    {
        $this->authorizeManage();

        Correspondence::findOrFail($correspondenceId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Courrier supprimé.');
    }

    /**
     * Fait passer le courrier au statut « traité ».
     */
    public function markAsProcessed(int $correspondenceId): void
    {
        $this->authorizeManage();

        $correspondence = Correspondence::findOrFail($correspondenceId);

        $correspondence->update([
            'status' => CorrespondenceStatus::Traite->value,
            'processed_at' => now(),
        ]);

        $this->logEvent($correspondence, 'traitement', 'Courrier marqué comme traité.');

        $this->dispatch('toast', type: 'success', message: 'Courrier marqué comme traité.');
    }

    /**
     * Clôture définitive du courrier.
     */
    public function close(int $correspondenceId): void
    {
        $this->authorizeManage();

        $correspondence = Correspondence::findOrFail($correspondenceId);

        $correspondence->update([
            'status' => CorrespondenceStatus::Clos->value,
            'closed_at' => now(),
        ]);

        $this->logEvent($correspondence, 'cloture', 'Courrier clôturé.');

        $this->dispatch('toast', type: 'success', message: 'Courrier clôturé.');
    }

    /**
     * Archivage du courrier.
     */
    public function archive(int $correspondenceId): void
    {
        $this->authorizeManage();

        $correspondence = Correspondence::findOrFail($correspondenceId);

        $correspondence->update(['status' => CorrespondenceStatus::Archive->value]);

        $this->logEvent($correspondence, 'archivage', 'Courrier archivé.');

        $this->dispatch('toast', type: 'success', message: 'Courrier archivé.');
    }

    /**
     * Enregistre une trace de traitement.
     */
    private function logEvent(Correspondence $correspondence, string $action, string $note): void
    {
        CorrespondenceEvent::create([
            'correspondence_id' => $correspondence->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'note' => $note,
        ]);
    }

    /**
     * Création d'un service destinataire.
     */
    public function addService(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'serviceName' => 'required|string|max:255',
            'serviceDescription' => 'nullable|string|max:1000',
        ]);

        $name = trim($validated['serviceName']);

        CorrespondenceService::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'description' => $validated['serviceDescription'] ?: null],
        );

        $this->reset(['serviceName', 'serviceDescription']);
        $this->showServiceForm = false;

        $this->dispatch('toast', type: 'success', message: 'Service ajouté.');
    }

    #[Computed]
    public function correspondences()
    {
        return Correspondence::query()
            ->with('service')
            ->with('assignedMember')
            ->when($this->overview === 'ouverts', fn ($q) => $q->whereIn('status', CorrespondenceStatus::open()))
            ->when($this->overview === 'confidentiels', fn ($q) => $q->where('confidentiality', 'confidentiel'))
            ->when($this->search !== '', fn (Builder $query) => $query->search($this->search))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->channel !== '', fn (Builder $query) => $query->where('channel', $this->channel))
            ->when($this->serviceId, fn (Builder $query) => $query->where('service_id', $this->serviceId))
            ->when($this->year !== '', fn (Builder $query) => $query->whereYear('correspondence_date', $this->year))
            ->orderByDesc('correspondence_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingCorrespondence(): ?Correspondence
    {
        return $this->editingId ? Correspondence::find($this->editingId) : null;
    }

    #[Computed]
    public function services()
    {
        return CorrespondenceService::with('responsible')->withCount('correspondences')->orderBy('name')->get();
    }

    #[Computed]
    public function assignees()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return CorrespondenceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return CorrespondenceStatus::options();
    }

    #[Computed]
    public function channelOptions(): array
    {
        return CorrespondenceChannel::options();
    }

    #[Computed]
    public function years(): array
    {
        return CalendarYears::for(new Correspondence, 'correspondence_date');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Correspondence::count(),
            'entrants' => Correspondence::where('type', CorrespondenceType::Entrant->value)->count(),
            'sortants' => Correspondence::where('type', CorrespondenceType::Sortant->value)->count(),
            'a_traiter' => Correspondence::whereIn('status', CorrespondenceStatus::open())->count(),
            'confidentiels' => Correspondence::where('confidentiality', 'confidentiel')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.correspondences.index');
    }
}
