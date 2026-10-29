<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GitHubActionsTokenVerifier
{
    private const ISSUER = 'https://token.actions.githubusercontent.com';

    public function verify(string $token): bool
    {
        try {
            $keys = Cache::remember('github-actions-oidc-jwks', now()->addHours(6), fn () => Http::acceptJson()
                ->timeout(10)->retry(2, 250)
                ->get(self::ISSUER.'/.well-known/jwks')
                ->throw()->json());
            $claims = JWT::decode($token, JWK::parseKeySet($keys));
            $audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? null];
            $repository = (string) config('chorale.reminders.oidc_repository');
            $workflow = $repository.'/.github/workflows/reminders.yml@refs/heads/main';

            return ($claims->iss ?? null) === self::ISSUER
                && in_array(config('chorale.reminders.oidc_audience'), $audiences, true)
                && ($claims->repository ?? null) === $repository
                && ($claims->ref ?? null) === 'refs/heads/main'
                && ($claims->workflow_ref ?? null) === $workflow
                && in_array($claims->event_name ?? null, ['schedule', 'workflow_dispatch'], true);
        } catch (Throwable $exception) {
            Log::notice('Jeton GitHub Actions refusé pour le scheduler.', ['exception' => $exception::class]);

            return false;
        }
    }
}
