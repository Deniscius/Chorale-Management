<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Client inactif.
 *
 * Utilisé lorsque l'IA est désactivée. Toute tentative de génération échoue
 * avec un message explicite plutôt que de produire un contenu factice.
 */
class NullAiClient implements AiClient
{
    public function complete(string $system, string $prompt): string
    {
        throw new RuntimeException(
            'L’assistant IA est désactivé. Activez AI_ENABLED et configurez un fournisseur.'
        );
    }
}
