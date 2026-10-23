<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client pour un modèle exécuté localement avec Ollama.
 *
 * Aucune donnée ne quitte le serveur : adapté aux chorales qui manipulent des
 * informations personnelles et souhaitent rester maîtresses de leur contenu.
 */
class OllamaClient implements AiClient
{
    public function complete(string $system, string $prompt): string
    {
        $config = config('ai.providers.ollama');

        $response = Http::timeout((int) config('ai.timeout', 45))
            ->acceptJson()
            ->post(rtrim($config['base_url'], '/').'/api/chat', [
                'model' => $config['model'],
                'stream' => false,
                'options' => [
                    'num_predict' => (int) config('ai.max_tokens', 700),
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Ollama a renvoyé une erreur (HTTP '.$response->status().').'
            );
        }

        $content = data_get($response->json(), 'message.content');

        if (blank($content)) {
            throw new RuntimeException('Ollama a renvoyé une réponse vide.');
        }

        return trim($content);
    }
}
