<?php

namespace App\Services;

/**
 * Génération des références des écritures comptables.
 *
 * Format : OP/2026/0001 (opération de l'année 2026).
 */
class TransactionReferenceGenerator
{
    public const PREFIX = 'OP';

    public function next(?int $year = null): string
    {
        return app(ReferenceSequence::class)->next('transactions', self::PREFIX, $year ?? now()->year);
    }
}
