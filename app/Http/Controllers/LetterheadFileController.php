<?php

namespace App\Http\Controllers;

use App\Models\LetterheadTemplate;
use App\Services\PrivateFiles;
use Illuminate\Http\Request;

final class LetterheadFileController
{
    public function __invoke(Request $request, LetterheadTemplate $template, string $kind, PrivateFiles $files)
    {
        abort_unless((int) $template->tenant_id === (int) $request->user()->tenant_id, 404);
        [$path, $name, $inline, $mime] = match ($kind) {
            'original' => [$template->original_path, $template->original_name, false, strtolower(pathinfo($template->original_name, PATHINFO_EXTENSION)) === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'preview' => [$template->preview_path, 'apercu-papier-entete.png', true, 'image/png'],
            'header' => [$template->header_path, 'papier-entete.png', true, 'image/png'],
            default => abort(404),
        };
        abort_unless($path, 404);

        return $files->response($path, $name, $inline, $mime);
    }
}
