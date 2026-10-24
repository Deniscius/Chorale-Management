<?php

namespace App\Http\Controllers;

use App\Services\ChoraleIdentity;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
    public function __invoke(ChoraleIdentity $identity): Response|BinaryFileResponse
    {
        $path = $identity->logoPath();

        abort_if($path === null, 404, "Aucun logo de chorale n'est défini.");

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => config('tenancy.enabled') ? 'private, no-store' : 'public, max-age=31536000, immutable',
            'ETag' => '"'.$identity->logoVersion().'"',
        ]);
    }
}
