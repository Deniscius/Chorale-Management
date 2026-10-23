<?php

/**
 * Configuration de l'assistant IA.
 *
 * L'IA est facultative et désactivée par défaut : l'application reste
 * pleinement fonctionnelle sans elle. Deux fournisseurs sont pris en charge :
 *
 *  - « openrouter » : API compatible OpenAI, service hébergé ;
 *  - « ollama »     : modèle exécuté localement, aucune donnée ne sort du serveur.
 *
 * Aucune donnée sensible (montants, coordonnées personnelles) n'est transmise
 * au fournisseur : seules les informations utiles à la rédaction le sont.
 */
return [

    'enabled' => (bool) env('AI_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'openrouter'),

    'providers' => [

        'openrouter' => [
            'base_url' => env('AI_OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('AI_OPENROUTER_KEY'),
            'model' => env('AI_OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku'),
            'site_url' => env('APP_URL', 'http://localhost'),
            'site_name' => env('APP_NAME', 'Gestion Chorale'),
        ],

        'ollama' => [
            'base_url' => env('AI_OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model' => env('AI_OLLAMA_MODEL', 'llama3.1'),
            'api_key' => null,
        ],

    ],

    'timeout' => (int) env('AI_TIMEOUT', 45),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 700),

    /*
     * Rôles autorisés à déclencher une génération. La gestion du module
     * concerné reste vérifiée en plus par les Gates de l'application.
     */
    'roles' => [
        'admin',
        'directeur',
        'commission_liturgique',
        'commission_suivie_formation',
    ],

];
