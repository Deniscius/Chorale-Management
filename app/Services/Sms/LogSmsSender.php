<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Passerelle SMS de développement.
 *
 * Le message est écrit dans le journal applicatif au lieu d'être envoyé.
 * Cela permet de vérifier le contenu, le destinataire et le parcours complet
 * sans dépendre d'un opérateur ni engager de coût.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): bool
    {
        Log::info('SMS simulé', [
            'to' => $to,
            'message' => $message,
        ]);

        return true;
    }
}
