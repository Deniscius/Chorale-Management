<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Native D7 SMS API. Acceptance does not confirm delivery to the handset. */
final class D7SmsSender implements SmsSender
{
    public const ENDPOINT = 'https://api.d7networks.com/messages/v1/send';

    public function send(string $to, string $message): bool
    {
        $token = trim((string) config('chorale.sms.d7.token'));
        $originator = trim((string) config('chorale.sms.d7.originator'));
        if ($token === '' || preg_match('/[^\x21-\x7e]/', $token) || $originator === '') {
            throw new DeliveryRejected('Configuration D7 incomplète : vérifiez le jeton API et l’expéditeur.');
        }
        $recipient = preg_replace('/[\s().-]+/u', '', $to) ?? '';
        if (str_starts_with($recipient, '00')) {
            $recipient = '+'.substr($recipient, 2);
        }
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/D', $recipient)) {
            throw new DeliveryRejected('Numéro SMS invalide : utilisez le format international, par exemple +228 suivi des 8 chiffres du numéro.');
        }
        if (trim($message) === '') {
            throw new DeliveryRejected('Le contenu du SMS est vide.');
        }

        try {
            // No retry: a timeout or server failure can follow a billable accepted send.
            $response = Http::connectTimeout(5)
                ->timeout(max(1, min(60, (int) config('chorale.sms.timeout', 15))))
                ->withOptions(['allow_redirects' => false])
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'messages' => [[
                        'channel' => 'sms',
                        'recipients' => [$recipient],
                        'content' => $message,
                        'msg_type' => 'text',
                        'data_coding' => 'auto',
                    ]],
                    'message_globals' => ['originator' => $originator],
                ]);
        } catch (ConnectionException) {
            // Raw transport errors can include the recipient, body or Authorization header.
            throw new RuntimeException('Connexion D7 interrompue. Vérifiez le journal D7 avant toute relance.');
        }

        if (in_array($response->status(), [400, 401, 402, 403, 404, 405, 413, 415, 422, 429], true)) {
            throw new DeliveryRejected('D7 a refusé le SMS (HTTP '.$response->status().'). Vérifiez le compte, le crédit et les paramètres.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Réponse D7 incertaine (HTTP '.$response->status().'). Vérifiez le journal D7 avant toute relance.');
        }
        if ($response->json('status') === 'rejected') {
            throw new DeliveryRejected('D7 a explicitement refusé le SMS.');
        }
        $requestId = $response->json('request_id');
        if ($response->json('status') !== 'accepted' || ! is_string($requestId) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,199}$/D', $requestId)) {
            throw new RuntimeException('Accusé de réception D7 inattendu. Vérifiez le journal D7 avant toute relance.');
        }

        // Retain the provider reference for operational reconciliation, without contact data.
        Log::info('SMS accepté par D7', ['request_id' => $requestId]);

        return true;
    }
}
