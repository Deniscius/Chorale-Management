<?php

namespace App\Http\Controllers;

use App\Services\ChoraleIdentity;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert le logo de la chorale stocké sur le disque privé.
 *
 * Le fichier n'est pas exposé publiquement : il est transmis à la volée
 * afin d'éviter un lien symbolique et de garder un contrôle d'accès clair.
 *
 * L'URL contient l'identité du client et une version ; aucune réponse ne doit
 * être réutilisée par un autre utilisateur ou après une déconnexion.
 */
class ChoraleLogoController extends Controller
{
    public function __invoke(ChoraleIdentity $identity): Response|BinaryFileResponse|StreamedResponse
    {
        $tenant = request()->query('tenant');
        abort_if($tenant !== null && (! is_string($tenant) || ! ctype_digit($tenant) || (int) $tenant !== app(TenantContext::class)->id()), 404);
        $path = $identity->logoPath();

        abort_if($path === null, 404, "Aucun logo de chorale n'est défini.");

        return app(PrivateFiles::class)->response($path, basename($path), true, null, [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Cookie',
            'ETag' => '"'.$identity->logoVersion().'"',
        ]);
    }
}
