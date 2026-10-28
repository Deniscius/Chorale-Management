<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class DocxSecurityInspector
{
    public function inspect(UploadedFile $file): void
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            $this->reject('Le document Word est illisible ou endommagé.');
        }
        try {
            if ($zip->numFiles > 600 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                $this->reject('Le fichier ne correspond pas à un document Word DOCX valide.');
            }
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $expanded += (int) ($stat['size'] ?? 0);
                if ($expanded > 50 * 1024 * 1024 || str_contains($name, '../') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    $this->reject('Le document Word contient une archive non sûre.');
                }
                if (preg_match('#(^|/)(vbaProject\.bin|embeddings)(/|$)#i', $name)) {
                    $this->reject('Les macros et objets incorporés ne sont pas autorisés.');
                }
                if (str_ends_with(strtolower($name), '.rels')) {
                    $xml = $zip->getFromIndex($index);
                    if (is_string($xml)) {
                        $this->inspectRelationships($xml);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** Les hyperliens textuels sont sûrs ; les ressources distantes restent bloquées. */
    private function inspectRelationships(string $xml): void
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $this->reject('Le document Word contient des relations illisibles.');
        }

        foreach ($document->getElementsByTagName('Relationship') as $relationship) {
            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') !== 0) {
                continue;
            }
            $type = $relationship->getAttribute('Type');
            $target = trim($relationship->getAttribute('Target'));
            $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
            if (str_ends_with($type, '/hyperlink') && in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                continue;
            }

            $this->reject('Les images, modèles et autres ressources externes ne sont pas autorisés. Incorporez-les directement dans le document Word.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
