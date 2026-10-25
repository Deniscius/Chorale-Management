<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class TrustDeploymentProxies extends TrustProxies
{
    public function handle(Request $request, Closure $next)
    {
        return parent::handle($request, function (Request $request) use ($next) {
            // Render terminates TLS. Use the explicitly configured HTTPS
            // origin without accepting forwarded IP or host headers from peers.
            $origin = (string) config('app.url');
            if (app()->isProduction() && config('deployment.canonical_https') && parse_url($origin, PHP_URL_SCHEME) === 'https') {
                $request->server->set('HTTPS', 'on');
                $request->server->set('SERVER_PORT', 443);
                URL::forceRootUrl($origin);
                URL::forceScheme('https');
            }

            return $next($request);
        });
    }

    protected function proxies(): array
    {
        return config('deployment.trusted_proxies', []);
    }
}
