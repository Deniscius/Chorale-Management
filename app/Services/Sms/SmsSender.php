<?php

namespace App\Services\Sms;

/**
 * Passerelle d'envoi de SMS.
 *
 * L'application ne dépend d'aucun opérateur en particulier : l'implémentation
 * concrète est résolue depuis la configuration. En développement, le
 * « LogSmsSender » écrit le message dans les journaux, ce qui permet de tester
 * le parcours complet sans coût ni dépendance externe.
 */
interface SmsSender
{
    /**
     * Envoie un message et renvoie vrai si l'opérateur l'a accepté.
     */
    public function send(string $to, string $message): bool;
}
