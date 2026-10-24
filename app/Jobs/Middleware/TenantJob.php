<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;

final class TenantJob
{
    public function handle(object $job, Closure $next): mixed
    {
        if (! TenantContext::isolated()) {
            return $next($job);
        }
        $tenant = isset($job->tenantId) ? Tenant::find($job->tenantId) : null;
        if (! $tenant?->hasAccess()) {
            return null;
        }

        return app(TenantContext::class)->run($tenant->id, fn () => $next($job));
    }
}
