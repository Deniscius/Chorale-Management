<?php

namespace App\Livewire\Documents;

use App\Enums\Commission;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentArchive;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dépôt ou modification d'un document d'archive.
 *
 * Les fichiers sont stockés sur le disque privé, dans un répertoire par
 * commission, et ne sont consultables que via la route de téléchargement
 * protégée.
 */
class Form extends Component
{
    use WithFileUploads;

    public ?Document $document = null;

    #[Validate('required')]
    public string $commission = '';

    #[Validate('required|string|max:150')]
    public string $title = '';

    #[Validate('nullable|string|max:2000')]
    public ?string $description = null;

    #[Validate('nullable|string|max:80')]
    public ?string $category = null;

    #[Validate('required|in:private,published')]
    public string $visibility = 'private';

    #[Validate('nullable|date')]
    public ?string $document_date = null;

    #[Locked]
    public ?int $correspondenceId = null;

    /**
     * Fichier en cours de téléversement (temporaire Livewire).
     */
    #[Validate('nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp|max:10240')]
    public $file = null;

    public function mount(?Document $document = null, ?string $commission = null, ?int $correspondenceId = null): void
    {
        $document = $document?->exists ? $document : null;
        $this->document = $document;
        $this->correspondenceId = $document?->correspondence_id ?? $correspondenceId;
        if ($this->correspondenceId) {
            Correspondence::findOrFail($this->correspondenceId);
        }

        if ($document) {
            $this->assertCanUpload($document->commission);

            $this->fill([
                'commission' => $document->commission->value,
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category,
                'visibility' => $document->visibility === 'published' ? 'published' : 'private',
                'document_date' => $document->document_date?->format('Y-m-d'),
            ]);

            return;
        }

        $first = $this->uploadableCommissions[0] ?? null;

        $this->commission = $commission ?? $first?->value ?? '';

        abort_unless(Commission::tryFrom($this->commission) !== null, 403);

        $this->assertCanUpload(Commission::from($this->commission));
    }

    public function save(): void
    {
        $commission = Commission::tryFrom($this->commission);

        abort_unless($commission !== null, 404);

        $this->assertCanUpload($commission);
        if ($this->document) {
            $source = $this->document->fresh();
            abort_unless($source, 404);
            $this->assertCanUpload($source->commission);
        }

        $this->validate();

        if (! $this->document && ! $this->file) {
            $this->addError('file', 'Sélectionnez un fichier à archiver.');

            return;
        }

        $data = [
            'commission' => $commission->value,
            'title' => $this->title,
            'description' => $this->description ?: null,
            'category' => $this->category ?: null,
            'visibility' => $this->visibility,
            'document_date' => $this->document_date ?: null,
            'correspondence_id' => $this->correspondenceId,
        ];

        $this->document = app(DocumentArchive::class)->save($this->document, $data, $this->file);
        $message = 'Document archivé.';

        $this->reset('file');

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    /**
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
            Commission::cases(),
            fn (Commission $commission): bool => $commission->canManageArchive($user),
        ));
    }

    #[Computed]
    public function commissionOptions(): array
    {
        $options = [];

        foreach ($this->uploadableCommissions as $commission) {
            $options[$commission->value] = $commission->label();
        }

        return $options;
    }

    #[Computed]
    public function categories(): array
    {
        return [
            'Compte rendu' => 'Compte rendu',
            'Rapport' => 'Rapport',
            'Statuts' => 'Statuts',
            'Budget' => 'Budget',
            'Convocation' => 'Convocation',
            'Partition' => 'Partition',
            'Programme de messe' => 'Programme de messe',
            'Courrier' => 'Courrier',
            'Contrat' => 'Contrat',
            'Autre' => 'Autre',
        ];
    }

    protected function assertCanUpload(Commission $commission): void
    {
        abort_unless($commission->canManageArchive(auth()->user()), 403,
            'Dépôt de documents réservé aux responsables de la commission.');
    }

    public function hydrate(): void
    {
        if ($this->document) {
            $this->assertCanUpload($this->document->fresh()->commission);
        }
    }

    public function render(): View
    {
        return view('livewire.documents.form');
    }
}
