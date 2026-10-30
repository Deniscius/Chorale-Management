<?php

namespace App\Livewire\Rehearsals;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Rehearsal;
use App\Services\ChoraleAssistant;
use App\Services\ManagementYearContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Identifiant de la répétition dont le compte rendu est affiché. */
    public ?int $reportId = null;

    /** Compte rendu actuellement proposé (brouillon modifiable). */
    public string $reportDraft = '';

    public bool $generatingReport = false;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['overview', 'search', 'type', 'status', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'period', 'overview']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $rehearsalId): void
    {
        $this->authorizeManage();

        $this->editingId = $rehearsalId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reportId = null;
        $this->reportDraft = '';
    }

    public function delete(int $rehearsalId): void
    {
        $this->authorizeManage();

        Rehearsal::findOrFail($rehearsalId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Répétition supprimée.');
    }

    #[Computed]
    public function rehearsals()
    {
        return Rehearsal::query()
            ->when(app(ManagementYearContext::class)->selected(), fn ($q, $selected) => $q->whereDate('date', '>=', $selected->starts_on)->whereDate('date', '<=', $selected->ends_on))
            ->with('works')
            ->withCount('attendances')
            ->when($this->overview === 'presences', fn ($q) => $q->whereHas('attendances', fn ($q) => $q->where('status', 'present')))
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('venue', $like)->orWhereLike('notes', $like);
                });
            })
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->period === 'avenir', fn (Builder $query) => $query->whereDate('date', '>=', today()))
            ->when($this->period === 'passee', fn (Builder $query) => $query->whereDate('date', '<', today()))
            ->orderByDesc('date')
            ->paginate(15);
    }

    #[Computed]
    public function editingRehearsal(): ?Rehearsal
    {
        return $this->editingId ? Rehearsal::with('works')->findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function typeOptions(): array
    {
        return RehearsalType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return EventStatus::options();
    }

    #[Computed]
    public function stats(): array
    {
        $row = Rehearsal::query()->selectRaw(
            'COUNT(*) AS total, COALESCE(SUM(CASE WHEN date >= ? THEN 1 ELSE 0 END), 0) AS avenir, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS terminees',
            [today()->toDateString(), EventStatus::Terminee->value]
        )->toBase()->first();

        return ['total' => (int) $row->total, 'avenir' => (int) $row->avenir, 'terminees' => (int) $row->terminees, 'presence' => (int) app(TenantContext::class)->table('rehearsal_attendances')->where('status', 'present')->count()];
    }

    /**
     * L'assistant IA est-il disponible pour l'utilisateur courant ?
     */
    #[Computed]
    public function aiEnabled(): bool
    {
        if (! config('ai.enabled') || ! $this->canManage()) {
            return false;
        }

        $role = auth()->user()?->role;

        return $role !== null && in_array($role->value, (array) config('ai.roles', []), true);
    }

    /**
     * Répétition dont le compte rendu est ouvert.
     */
    #[Computed]
    public function reportRehearsal(): ?Rehearsal
    {
        return $this->reportId ? Rehearsal::with('works')->findOrFail($this->reportId) : null;
    }

    /**
     * Ouvre la fenêtre de compte rendu : brouillon IA existant ou vide.
     */
    public function openReport(int $rehearsalId): void
    {
        $this->authorizeManage();

        abort_unless($this->aiEnabled, 403, 'Assistant IA indisponible.');

        $rehearsal = Rehearsal::findOrFail($rehearsalId);

        $this->reportId = $rehearsal->getKey();
        $this->reportDraft = (string) ($rehearsal->ai_report ?? '');
    }

    /**
     * Demande une proposition de compte rendu à l'assistant.
     */
    #[Computed]
    public function reportContext(): string
    {
        return $this->reportRehearsal ? app(ChoraleAssistant::class)->rehearsalPrompt($this->reportRehearsal) : '';
    }

    public function generateReport(): void
    {
        $this->authorizeManage();

        abort_unless($this->aiEnabled, 403, 'Assistant IA indisponible.');

        $rehearsal = $this->reportRehearsal;

        if (! $rehearsal) {
            return;
        }

        $this->generatingReport = true;

        try {
            $this->reportDraft = app(ChoraleAssistant::class)->generateRehearsalReport($rehearsal);
        } catch (Throwable $exception) {
            Log::warning('Génération du compte rendu indisponible.', ['tenant_id' => app(TenantContext::class)->requireId(), 'rehearsal_id' => $rehearsal->id, 'exception' => get_class($exception)]);
            $this->dispatch('toast', type: 'error', message: 'La génération est momentanément indisponible. Votre brouillon est conservé. Réessayez plus tard.');
        } finally {
            $this->generatingReport = false;
        }
    }

    /**
     * Enregistre le compte rendu, après relecture humaine.
     */
    public function saveReport(): void
    {
        $this->authorizeManage();

        abort_unless($this->aiEnabled, 403, 'Assistant IA indisponible.');

        $this->validate([
            'reportDraft' => ['nullable', 'string', 'max:20000'],
        ], attributes: ['reportDraft' => 'compte rendu']);

        $rehearsal = $this->reportRehearsal;

        if (! $rehearsal) {
            return;
        }

        $rehearsal->forceFill([
            'ai_report' => $this->reportDraft !== '' ? $this->reportDraft : null,
            'ai_report_generated_at' => $this->reportDraft !== '' ? now() : null,
        ])->save();

        $this->dispatch('toast', type: 'success', message: 'Compte rendu enregistré.');
        $this->closeReport();
    }

    /**
     * Réinitialise l'état de la fenêtre de compte rendu.
     */
    public function closeReport(): void
    {
        $this->reportId = null;
        $this->reportDraft = '';
    }

    public function render(): View
    {
        return view('livewire.rehearsals.index');
    }
}
