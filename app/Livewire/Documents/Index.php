<?php

namespace App\Livewire\Documents;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\CommissionTeam;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentArchive;
use App\Services\PrivateFiles;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Archives documentaires des commissions.
 *
 * Chaque commission dispose de son espace. La visibilité du document
 * limite la consultation ; les responsables habilités conservent seuls
 * le dépôt, la modification, le partage et la suppression.
 */
class Index extends Component
{
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_MEMBERS;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'commission', except: '')]
    public string $commission = '';

    #[Url(as: 'categorie', except: '')]
    public string $category = '';

    #[Url(as: 'visibilite', except: '')]
    public string $visibility = '';

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    #[Locked]
    public ?int $correspondenceId = null;

    public bool $showTeam = false;

    #[Locked]
    public ?int $visibilityDocumentId = null;

    #[Locked]
    public ?int $previewDocumentId = null;

    public function preview(int $id): void
    {
        $document = Document::findOrFail($id);
        abort_unless($document->canView(auth()->user()), 403);
        abort_unless($document->isPreviewable(), 415);
        $this->previewDocumentId = $document->id;
    }

    #[On('close-preview')]
    public function closePreview(): void
    {
        $this->previewDocumentId = null;
    }

    #[Computed]
    public function previewDocument(): ?Document
    {
        if (! $this->previewDocumentId) {
            return null;
        }

        $document = Document::findOrFail($this->previewDocumentId);
        abort_unless($document->canView(auth()->user()), 403);
        abort_unless($document->isPreviewable(), 415);

        return $document;
    }

    public function changeVisibility(int $id): void
    {
        $document = Document::findOrFail($id);
        abort_unless($document->canManage(auth()->user()), 403);
        $this->visibilityDocumentId = $document->id;
    }

    #[On('close-visibility')]
    public function closeVisibility(): void
    {
        $this->visibilityDocumentId = null;
    }

    #[Computed]
    public function visibilityDocument(): ?Document
    {
        return $this->visibilityDocumentId ? Document::findOrFail($this->visibilityDocumentId) : null;
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(?Correspondence $correspondence = null): void
    {
        abort_unless(auth()->check(), 403);
        if ($correspondence?->exists) {
            abort_unless(auth()->user()->canAccessModule(UserRole::MODULE_COURRIERS) || Commission::Secretariat->canManageArchive(auth()->user()), 403);
            $this->correspondenceId = $correspondence->id;
            $this->commission = Commission::Secretariat->value;
        }

        // Par défaut, on ouvre la première commission accessible.
        if ($this->commission === '') {
            $first = $this->allowedCommissions[0] ?? null;
            $this->commission = $first?->value ?? '';
        }

        $this->assertAllowedCommission($this->commission);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'commission', 'category', 'visibility', 'year'], true)) {
            $this->resetPage();
        }

        if ($property === 'commission') {
            $this->assertAllowedCommission($this->commission);
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'visibility', 'year']);
        $this->resetPage();
    }

    #[On('close-team')]
    public function closeTeam(): void
    {
        $this->showTeam = false;
        unset($this->teams);
    }

    public function configureTeam(): void
    {
        abort_unless($this->canConfigureTeam, 403);
        $this->showTeam = true;
    }

    public function create(): void
    {
        $this->assertCanUpload($this->commission);

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $documentId): void
    {
        $document = Document::findOrFail($documentId);

        $this->assertCanUpload($document->commission->value);

        $this->editingId = $documentId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->showTeam = false;
        $this->editingId = null;
    }

    public function delete(int $documentId): void
    {
        $document = Document::findOrFail($documentId);

        $this->assertCanUpload($document->commission->value);

        app(DocumentArchive::class)->delete($document);

        $this->dispatch('toast', type: 'success', message: 'Document retiré de l\'archive.');
    }

    /**
     * Commissions accessibles à l'utilisateur courant.
     *
     * @return array<int, Commission>
     */
    #[Computed]
    public function allowedCommissions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        if ($this->correspondenceId) {
            return [Commission::Secretariat];
        }

        // Chaque espace peut contenir un document partagé avec toute la chorale.
        // Le filtre readable protège les titres, compteurs et catégories.
        return Commission::cases();
    }

    /**
     * Commissions sur lesquelles l'utilisateur peut déposer un document.
     *
     * @return array<int, Commission>
     */
    #[Computed]
    public function uploadableCommissions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return array_values(array_filter(
            $this->allowedCommissions,
            fn (Commission $commission): bool => $this->canUpload($commission),
        ));
    }

    #[Computed]
    public function canUploadCurrent(): bool
    {
        $commission = Commission::tryFrom($this->commission);

        return $commission !== null && $this->canUpload($commission);
    }

    public function canUpload(Commission $commission): bool
    {
        return $commission->canManageArchive(auth()->user(), $this->teams->get($commission->value));
    }

    #[Computed]
    public function documents()
    {
        $documents = Document::query()
            ->with(['uploader', 'member', 'team'])
            ->readable(auth()->user())
            ->when($this->correspondenceId, fn ($q) => $q->where('correspondence_id', $this->correspondenceId))
            ->when($this->visibility !== '', fn ($q) => $q->where('visibility', $this->visibility))
            ->when($this->year !== '', fn ($q) => $q->whereYear('document_date', ctype_digit($this->year) && strlen($this->year) === 4 ? (int) $this->year : 0))
            ->forCommission($this->commission)
            ->search($this->search)
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12);
        app(PrivateFiles::class)->primeExists($documents->pluck('path')->all());

        return $documents;
    }

    #[Computed]
    public function editingDocument(): ?Document
    {
        return $this->editingId ? Document::findOrFail($this->editingId) : null;
    }

    #[Computed]
    public function categories(): array
    {
        return Document::query()->readable(auth()->user())
            ->when($this->correspondenceId, fn ($q) => $q->where('correspondence_id', $this->correspondenceId))
            ->forCommission($this->commission)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    #[Computed]
    public function stats(): array
    {
        $base = Document::query()->readable(auth()->user())->forCommission($this->commission)->when($this->correspondenceId, fn ($q) => $q->where('correspondence_id', $this->correspondenceId));

        $row = (clone $base)->selectRaw("COUNT(*) as total, COALESCE(SUM(size),0) as octets, COALESCE(SUM(CASE WHEN visibility = 'private' THEN 1 ELSE 0 END),0) as prives, COALESCE(SUM(CASE WHEN visibility = 'published' THEN 1 ELSE 0 END),0) as partages")->first();

        return ['total' => (int) $row->total, 'octets' => (int) $row->octets, 'prives' => (int) $row->prives, 'partages' => (int) $row->partages, 'commissions' => count($this->allowedCommissions)];
    }

    /**
     * Nombre de documents par commission accessible (affiché en onglets).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function countsByCommission(): array
    {
        $values = array_map(fn (Commission $c): string => $c->value, $this->allowedCommissions);

        return Document::query()->readable(auth()->user())
            ->selectRaw('commission, count(*) as total')
            ->whereIn('commission', $values)
            ->when($this->correspondenceId, fn ($q) => $q->where('correspondence_id', $this->correspondenceId))
            ->groupBy('commission')
            ->pluck('total', 'commission')
            ->all();
    }

    protected function assertAllowedCommission(string $value): void
    {
        $allowed = array_map(fn (Commission $c): string => $c->value, $this->allowedCommissions);

        abort_unless(in_array($value, $allowed, true), 403, 'Commission non accessible.');
    }

    protected function assertCanUpload(string $value): void
    {
        $commission = Commission::tryFrom($value);

        abort_unless($commission !== null, 404);

        $this->assertAllowedCommission($value);

        abort_unless($this->canUpload($commission), 403, 'Dépôt de documents réservé aux responsables de la commission.');
    }

    #[Computed]
    public function teams()
    {
        return CommissionTeam::all()->keyBy(fn ($team) => $team->commission->value);
    }

    #[Computed]
    public function canConfigureTeam(): bool
    {
        $commission = Commission::tryFrom($this->commission);
        if (! $commission?->hasPrivateArchive()) {
            return false;
        }

        return auth()->user()?->isAdmin() || $this->teams->get($this->commission)?->leader_id === auth()->id();
    }

    public function share(int $id, bool $published): void
    {
        app(DocumentArchive::class)->share($id, $published);
        $this->dispatch('toast', type: 'success', message: $published ? 'Document partagé avec les utilisateurs connectés.' : 'Document à nouveau privé.');
    }

    public function render(): View
    {
        $this->assertAllowedCommission($this->commission);

        return view('livewire.documents.index');
    }
}
