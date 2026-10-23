<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client compatible OpenAI, utilisé pour OpenRouter.
 *
 * OpenRouter expose une API « chat completions » identique à celle d'OpenAI.
 * Les en-têtes HTTP-Referer et X-Title sont recommandés par le service pour
 * identifier l'application consommatrice.
 */
class OpenRouterClient implements AiClient
{
    public function complete(string $system, string $prompt): string
    {
        $config = config('ai.providers.openrouter');

        if (blank($config['api_key'] ?? null)) {
            throw new RuntimeException('La clé OpenRouter n’est pas configurée (AI_OPENROUTER_KEY).');
        }

        $response = Http::timeout((int) config('ai.timeout', 45))
            ->withToken($config['api_key'])
            ->withHeaders([
                'HTTP-Referer' => $config['site_url'] ?? config('app.url'),
                'X-Title' => $config['site_name'] ?? config('app.name'),
            ])
            ->acceptJson()
            ->post(rtrim($config['base_url'], '/').'/chat/completions', [
                'model' => $config['model'],
                'max_tokens' => (int) config('ai.max_tokens', 700),
                'temperature' => 0.4,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Le fournisseur IA a renvoyé une erreur (HTTP '.$response->status().').'
            );
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (blank($content)) {
            throw new RuntimeException('Le fournisseur IA a renvoyé une réponse vide.');
        }

        return trim($content);
    }
}
