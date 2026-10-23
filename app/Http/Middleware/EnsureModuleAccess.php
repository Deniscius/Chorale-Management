<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'accès à un module fonctionnel selon le rôle de l'utilisateur.
 *
 * Usage : ->middleware('module:members')
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        abort_unless($user->canAccessModule($module), 403, "Accès refusé au module « {$module} ».");

        return $next($request);
    }
}
