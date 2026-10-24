<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RecordPlatformUsage
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::guard('web')->user();
        $tenant = app(TenantContext::class)->id();
        if (! config('tenancy.enabled') || $request->routeIs('platform.*') || ! $user || (int) $user->tenant_id !== $tenant) {
            return $next($request);
        }
        $start = hrtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();
            throw $e;
        } catch (Throwable $e) {
            $status = match (true) {
                $e instanceof ValidationException => $e->status,
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                default => 500,
            };
            throw $e;
        } finally {
            $ms = max(0, (int) round((hrtime(true) - $start) / 1e6));
            $row = ['tenant_id' => $tenant, 'user_id' => $user->id, 'day' => today()->toDateString(), 'requests' => 1, 'errors' => (int) ($status >= 500), 'slow_requests' => (int) ($ms >= 1500), 'duration_ms' => $ms];
            $updates = [];
            foreach (['requests', 'errors', 'slow_requests', 'duration_ms'] as $field) {
                $updates[$field] = DB::raw('platform_usage_daily.'.$field.' + '.(int) $row[$field]);
            }
            try {
                DB::table('platform_usage_daily')->upsert([$row], ['tenant_id', 'user_id', 'day'], $updates);
            } catch (Throwable $e) {
                // Telemetry must never interrupt a client's request or log its payload.
                Log::warning('Platform usage recording unavailable.');
            }
        }
    }
}
