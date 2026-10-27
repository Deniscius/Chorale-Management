<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PrivateFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement explicite d'un document archivé.
 *
 * L'accès respecte la visibilité du document et les responsabilités de
 * l'utilisateur. Le fichier reste privé et n'est jamais exposé directement.
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
            false,
            $document->mime_type,
        );
    }
}
