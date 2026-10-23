<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
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
            $document->commission->canView(auth()->user()),
            403,
            'Document non accessible avec votre rôle.',
        );

        abort_unless(Storage::disk('local')->exists($document->path), 404, 'Fichier introuvable.');

        $path = Storage::disk('local')->path($document->path);

        // Consultation en ligne pour les formats que le navigateur sait afficher.
        if (in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->file($path, [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'inline; filename="'.$document->original_name.'"',
            ]);
        }

        return response()->download($path, $document->original_name);
    }
}
