<?php

namespace App\Services;

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
                    if (is_string($xml) && preg_match('/TargetMode\s*=\s*["\']External["\']/i', $xml)) {
                        $this->reject('Les liens vers des ressources externes ne sont pas autorisés.');
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
