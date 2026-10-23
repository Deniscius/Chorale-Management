<?php

namespace App\Services\Ai;

/**
 * Contrat minimal d'un fournisseur de modèle de langage.
 *
 * L'application n'a besoin que d'une seule opération : produire un texte à
 * partir d'une consigne système et d'un message utilisateur. Cette abstraction
 * permet de changer de fournisseur (OpenRouter, Ollama, autre) sans toucher
 * au code métier, et de remplacer le client par un double dans les tests.
 */
interface AiClient
{
    /**
     * Renvoie la réponse du modèle, sans espaces superflus.
     */
    public function complete(string $system, string $prompt): string;
}
