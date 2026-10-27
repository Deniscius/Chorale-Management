<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Services\DocumentArchive;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class VisibilityForm extends Component
{
    #[Locked]
    public int $documentId;

    #[Locked]
    public string $originalVisibility;

    public string $visibility = 'private';

    public function mount(Document $document): void
    {
        abort_unless($document->canManage(auth()->user()), 403);
        $this->documentId = $document->id;
        $this->originalVisibility = $document->visibility;
        $this->visibility = DocumentVisibility::tryFrom($document->visibility)?->value ?? 'private';
    }

    public function hydrate(): void
    {
        abort_unless(Document::findOrFail($this->documentId)->canManage(auth()->user()), 403);
    }

    public function save(): void
    {
        $this->validate(['visibility' => ['required', Rule::in(array_keys(DocumentVisibility::labels()))]]);
        app(DocumentArchive::class)->setVisibility($this->documentId, $this->visibility, $this->originalVisibility);
        $this->dispatch('toast', type: 'success', message: 'Visibilité du document enregistrée.');
        $this->dispatch('close-visibility');
    }

    public function render()
    {
        return view('livewire.documents.visibility-form');
    }
}
