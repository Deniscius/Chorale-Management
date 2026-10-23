<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Str;

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
        $year ??= now()->year;

        $last = Transaction::where('reference', 'like', self::PREFIX.'/'.$year.'/%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last ? ((int) Str::afterLast($last, '/')) + 1 : 1;

        return sprintf('%s/%d/%04d', self::PREFIX, $year, $sequence);
    }
}
