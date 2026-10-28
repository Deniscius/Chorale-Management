<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Services\PrivateFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InstrumentImageController extends Controller
{
    public function __invoke(Instrument $instrument): BinaryFileResponse|StreamedResponse
    {
        abort_unless($instrument->image_path, 404);
        $mime = match (strtolower(pathinfo($instrument->image_path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => abort(415, 'Format d’image non pris en charge.'),
        };

        return app(PrivateFiles::class)->response(
            $instrument->image_path,
            'instrument-'.$instrument->id.'.'.pathinfo($instrument->image_path, PATHINFO_EXTENSION),
            true,
            $mime,
            ['Cache-Control' => 'private, no-store', 'X-Frame-Options' => 'SAMEORIGIN'],
        );
    }
}
