<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

final class EstablishTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->routeIs('platform.*')) {
            // Platform authentication never establishes a client's data context.
            app(TenantContext::class)->set(null);
            try {
                return $next($request);
            } finally {
                app(TenantContext::class)->set(null);
            }
        }
        if (! config('tenancy.enabled')) {
            abort_unless(app()->runningUnitTests(), 503, 'Le mode multi-tenant doit être activé avant la mise en service.');

            return $next($request);
        }
        $context = app(TenantContext::class);
        $context->set($request->user()?->tenant_id);
        $uploadDirectory = config('livewire.temporary_file_upload.directory');
        if ($context->id() !== null) {
            config(['livewire.temporary_file_upload.directory' => $context->path('livewire-tmp')]);
        }
        try {
            if ($request->user()) {
                $tenant = Tenant::findOrFail($context->requireId());
                $request->attributes->set('tenant', $tenant);
                if (! $tenant->hasAccess() && ! $request->routeIs('billing.*', 'logout', 'verification.*')) {
                    if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                        return response()->json(['message' => 'Votre accès a expiré.', 'redirect' => route('billing.show')], 402, ['Cache-Control' => 'no-store, private']);
                    }

                    return redirect()->route('billing.show')->header('Cache-Control', 'no-store, private');
                }
            }

            $response = $next($request);
            if ($request->user()) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }

            return $response;
        } finally {
            config(['livewire.temporary_file_upload.directory' => $uploadDirectory]);
            $context->set(null);
        }
    }
}
