<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class PdfSecurityInspector
{
    public function inspect(UploadedFile $file): void
    {
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents) || ! str_starts_with($contents, '%PDF-')) {
            $this->reject('Le fichier ne correspond pas à un document PDF valide.');
        }

        // Poppler produit une image statique et n’exécute pas ces actions. Elles
        // sont néanmoins refusées pour ne pas conserver un original interactif.
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b#i', $contents)) {
            $this->reject('Les scripts, actions automatiques et fichiers incorporés ne sont pas autorisés dans le PDF.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
