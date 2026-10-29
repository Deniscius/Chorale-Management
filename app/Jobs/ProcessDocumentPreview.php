<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentPreviewProcessor;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Queue\Queueable;

/** Exécuté après la réponse HTTP sur le serveur qui possède le fichier privé. */
final class ProcessDocumentPreview
{
    use Queueable;

    public function __construct(public readonly int $tenantId, public readonly int $documentId) {}

    public function handle(TenantContext $tenant, DocumentPreviewProcessor $processor): void
    {
        $tenant->run($this->tenantId, function () use ($processor): void {
            $document = Document::query()->find($this->documentId);
            if ($document) {
                $processor->process($document);
            }
        });
    }
}
