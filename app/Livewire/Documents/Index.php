<?php

namespace App\Livewire\Documents;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentArchive;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Archives documentaires des commissions.
 *
 * Chaque commission dispose de son espace : la consultation est ouverte
 * aux rôles ayant accès au module, le dépôt et la suppression restent
 * réservés aux rôles disposant du droit d'écriture sur ce module.
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

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        // Par défaut, on ouvre la première commission accessible.
        if ($this->commission === '') {
            $first = $this->allowedCommissions[0] ?? null;
            $this->commission = $first?->value ?? '';
        }

        $this->assertAllowedCommission($this->commission);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'commission', 'category'], true)) {
            $this->resetPage();
        }

        if ($property === 'commission') {
            $this->assertAllowedCommission($this->commission);
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category']);
        $this->resetPage();
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

        return Commission::forRole($user->role);
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
            fn (Commission $commission): bool => $user->canManageModule($commission->module()),
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
        return $commission->canManage(auth()->user());
    }

    #[Computed]
    public function documents()
    {
        return Document::query()
            ->with(['uploader', 'member'])
            ->forCommission($this->commission)
            ->search($this->search)
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12);
    }

    #[Computed]
    public function editingDocument(): ?Document
    {
        return $this->editingId ? Document::find($this->editingId) : null;
    }

    #[Computed]
    public function categories(): array
    {
        return Document::query()
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
        $base = Document::query()->forCommission($this->commission);

        return [
            'total' => (clone $base)->count(),
            'octets' => (int) (clone $base)->sum('size'),
            'commissions' => count($this->allowedCommissions),
        ];
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

        return Document::query()
            ->selectRaw('commission, count(*) as total')
            ->whereIn('commission', $values)
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

    public function render(): View
    {
        $this->assertAllowedCommission($this->commission);

        return view('livewire.documents.index');
    }
}
