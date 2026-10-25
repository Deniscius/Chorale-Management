<?php

namespace App\Http\Controllers;

use App\Services\ChoraleIdentity;
use App\Services\PrivateFiles;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert le logo de la chorale stocké sur le disque privé.
 *
 * Le fichier n'est pas exposé publiquement : il est transmis à la volée
 * afin d'éviter un lien symbolique et de garder un contrôle d'accès clair.
 *
 * L'URL étant versionnée (`?v=empreinte`), la réponse peut être mise en cache
 * très longtemps par le navigateur : un nouveau logo change d'URL.
 */
class ChoraleLogoController extends Controller
{
    public function __invoke(ChoraleIdentity $identity): Response|BinaryFileResponse|StreamedResponse
    {
        $path = $identity->logoPath();

        abort_if($path === null, 404, "Aucun logo de chorale n'est défini.");

        return app(PrivateFiles::class)->response($path, basename($path), true, null, [
            'Cache-Control' => config('tenancy.enabled') ? 'private, no-store' : 'public, max-age=31536000, immutable',
            'ETag' => '"'.$identity->logoVersion().'"',
        ]);
    }
}
