<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChurchProjectClient
{
    public function enabled(): bool
    {
        return (bool) config('services.church_project.enabled');
    }

    public function resolveInvitation(string $token): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()->asJson()->connectTimeout(3)->timeout($this->timeout())
                ->withoutRedirecting()
                ->retry(2, 200, throw: false)
                ->post('/integrations/chorale-management/invitations/resolve', ['token' => $token]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['invitation' => 'Church Project est temporairement indisponible. Réessayez dans quelques instants.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['invitation' => 'Cette invitation est invalide, expirée ou déjà utilisée.']);
        }

        return Validator::make($response->json(), [
            'choralePublicId' => ['required', 'uuid'],
            'choraleNom' => ['required', 'string', 'max:150'],
            'paroissePublicId' => ['required', 'uuid'],
            'paroisseNom' => ['required', 'string', 'max:150'],
            'doyennePublicId' => ['required', 'uuid'],
            'doyenneNom' => ['required', 'string', 'max:400'],
        ])->validate();
    }

    public function consumeInvitation(string $token): void
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()->asJson()->connectTimeout(3)->timeout($this->timeout())
                ->withoutRedirecting()
                ->post('/integrations/chorale-management/invitations/consume', ['token' => $token]);
            if (! $response->successful()) {
                Log::warning('Church Project invitation could not be marked as used', ['status' => $response->status()]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Church Project invitation consumption failed', ['exception' => $exception::class]);
        }
    }

    private function baseUrl(): string
    {
        $url = rtrim(trim((string) config('services.church_project.url')), '/');
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $localDevelopment = app()->environment(['local', 'testing'])
            && $scheme === 'http'
            && in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
        $publicIp = ! filter_var($host, FILTER_VALIDATE_IP)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)
            || ($scheme !== 'https' && ! $localDevelopment)
            || $host === '' || ! $publicIp
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages([
                'invitation' => 'L’intégration Church Project n’est pas configurée de manière sécurisée.',
            ]);
        }

        return $url;
    }

    private function timeout(): int
    {
        return max(1, min(30, (int) config('services.church_project.timeout', 8)));
    }
}
