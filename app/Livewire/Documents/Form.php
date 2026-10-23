<?php

namespace App\Livewire\Documents;

use App\Enums\Commission;
use App\Models\Choir;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
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

    /**
     * Fichier en cours de téléversement (temporaire Livewire).
     */
    #[Validate('nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp|max:10240')]
    public $file = null;

    public function mount(?Document $document = null, ?string $commission = null): void
    {
        $this->document = $document;

        if ($document) {
            $this->assertCanUpload($document->commission);

            $this->fill([
                'commission' => $document->commission->value,
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category,
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
        ];

        if ($this->file) {
            $stored = $this->file->store('documents/'.$commission->value, 'local');

            // Remplace l'ancien fichier pour éviter les orphelins.
            if ($this->document) {
                Storage::disk('local')->delete($this->document->path);
            }

            $data = array_merge($data, [
                'original_name' => $this->file->getClientOriginalName(),
                'path' => $stored,
                'mime_type' => $this->file->getMimeType(),
                'size' => $this->file->getSize(),
            ]);
        }

        if ($this->document) {
            $this->document->update($data);
            $message = 'Document mis à jour.';
        } else {
            $data['choir_id'] = Choir::query()->value('id');
            $data['uploaded_by'] = auth()->id();
            $data['member_id'] = null;
            $this->document = Document::create($data);
            $message = 'Document archivé.';
        }

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
            Commission::forRole($user->role),
            fn (Commission $commission): bool => $user->canManageModule($commission->module()),
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
            'Contrat' => 'Contrat',
            'Autre' => 'Autre',
        ];
    }

    protected function assertCanUpload(Commission $commission): void
    {
        abort_unless(auth()->user()?->canManageModule($commission->module()), 403,
            "Dépôt de documents réservé aux responsables de la commission.");
    }

    public function render(): View
    {
        return view('livewire.documents.form');
    }
}
