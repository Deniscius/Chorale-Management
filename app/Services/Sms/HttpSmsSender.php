<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Passerelle SMS HTTP générique.
 *
 * L'application reste indépendante de l'opérateur : elle envoie un simple
 * corps JSON `{ from, to, message }` à l'URL configurée, authentifié par un
 * jeton Bearer. La plupart des agrégateurs (Orange, Twilio via passerelle,
 * Vonage, fournisseurs locaux) peuvent être branchés ainsi, directement ou
 * derrière un petit adaptateur.
 */
class HttpSmsSender implements SmsSender
{
    public function send(string $to, string $message): bool
    {
        $endpoint = config('chorale.sms.endpoint');

        if (blank($endpoint)) {
            throw new RuntimeException('Aucun point d’entrée SMS n’est configuré (SMS_ENDPOINT).');
        }

        $response = Http::timeout((int) config('chorale.sms.timeout', 15))
            ->withToken((string) config('chorale.sms.token'))
            ->acceptJson()
            ->post($endpoint, [
                'from' => config('chorale.reminders.sms_from'),
                'to' => $to,
                'message' => $message,
            ]);

        if ($response->clientError()) {
            throw new DeliveryRejected('L’opérateur SMS a refusé le message (HTTP '.$response->status().').');
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'L’opérateur SMS a refusé le message (HTTP '.$response->status().').'
            );
        }

        return true;
    }
}
