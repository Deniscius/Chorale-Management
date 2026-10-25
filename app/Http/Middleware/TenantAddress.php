<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

final class TenantAddress
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = Tenant::where('slug', $request->route('slug'))->firstOrFail();
        abort_if($request->user() && (int) $request->user()->tenant_id !== (int) $tenant->id, 403, 'Cette adresse appartient à une autre chorale.');
        $request->attributes->set('access_tenant', $tenant);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
