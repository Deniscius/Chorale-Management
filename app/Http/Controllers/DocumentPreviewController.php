<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\PrivateFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aperçu privé des formats passifs que le navigateur peut afficher.
 */
final class DocumentPreviewController extends Controller
{
    public function __invoke(Document $document): BinaryFileResponse|StreamedResponse
    {
        abort_unless($document->canView(auth()->user()), 403, 'Document non accessible avec votre rôle.');

        $mime = $document->previewMimeType();
        abort_unless($mime !== null, 415, 'Ce format ne permet pas un aperçu sécurisé.');

        return app(PrivateFiles::class)->response(
            $document->previewFilePath(),
            $document->preview_status === 'ready' ? pathinfo($document->original_name, PATHINFO_FILENAME).'.pdf' : $document->original_name,
            true,
            $mime,
            [
                'Content-Security-Policy' => "frame-ancestors 'self'",
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'no-referrer',
            ],
        );
    }
}
