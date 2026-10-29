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
            $response = Http::baseUrl(rtrim((string) config('services.church_project.url'), '/'))
                ->acceptJson()->asJson()->timeout((int) config('services.church_project.timeout', 8))
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
            $response = Http::baseUrl(rtrim((string) config('services.church_project.url'), '/'))
                ->acceptJson()->asJson()->timeout((int) config('services.church_project.timeout', 8))
                ->post('/integrations/chorale-management/invitations/consume', ['token' => $token]);
            if (! $response->successful()) {
                Log::warning('Church Project invitation could not be marked as used', ['status' => $response->status()]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Church Project invitation consumption failed', ['exception' => $exception::class]);
        }
    }
}
