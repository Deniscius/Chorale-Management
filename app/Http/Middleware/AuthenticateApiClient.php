<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string ...$abilities): mixed
    {
        $token = $request->bearerToken();
        if (! is_string($token) || ! str_starts_with($token, 'church_') || strlen($token) < 40) {
            return $this->unauthorized('Jeton API absent ou invalide.');
        }

        // Le jeton permet d'établir le tenant : cette unique recherche précède
        // volontairement le scope, puis toutes les données métier restent scopées.
        $client = ApiClient::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->first();
        if (! $client || $client->revoked_at || ($client->expires_at && $client->expires_at->isPast())) {
            return $this->unauthorized('Ce jeton API est invalide, expiré ou révoqué.');
        }
        foreach ($abilities as $ability) {
            if (! $client->can($ability)) {
                return response()->json(['message' => 'Ce jeton ne possède pas la permission '.$ability.'.'], 403);
            }
        }

        $tenant = Tenant::query()->find($client->tenant_id);
        if (! $tenant || ! $tenant->hasAccess()) {
            return response()->json(['message' => 'L’espace de la chorale n’est pas actif.'], 403);
        }

        $context = app(TenantContext::class);
        $context->set($client->tenant_id);
        $request->attributes->set('apiClient', $client);
        $request->attributes->set('tenant', $tenant);
        try {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        } finally {
            $context->set(null);
        }
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
