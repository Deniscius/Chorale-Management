<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PrivateFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultation d'un document archivé.
 *
 * L'accès est conditionné au droit de consultation du module auquel la
 * commission du document est rattachée. Le fichier reste sur le disque
 * privé et n'est jamais exposé directement.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document): BinaryFileResponse|StreamedResponse
    {
        abort_unless(
            $document->canView(auth()->user()),
            403,
            'Document non accessible avec votre rôle.',
        );

        return app(PrivateFiles::class)->response(
            $document->path, $document->original_name,
            in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true),
            $document->mime_type,
        );
    }
}
