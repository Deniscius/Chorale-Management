<?php

namespace App\Services;

use App\Enums\CorrespondenceType;
use App\Models\Correspondence;
use Illuminate\Support\Str;

/**
 * Génération des références (chronos) du registre de courrier.
 *
 * Format : CE/2026/0001 pour un courrier entrant, CS/2026/0001 pour un sortant.
 */
class CorrespondenceReferenceGenerator
{
    /**
     * Produit la prochaine référence disponible pour le type et l'année donnés.
     */
    public function next(CorrespondenceType $type, ?int $year = null): string
    {
        $year ??= now()->year;
        $prefix = $type->referencePrefix();

        $last = Correspondence::withTrashed()
            ->where('reference', 'like', "{$prefix}/{$year}/%")
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last
            ? ((int) Str::afterLast($last, '/')) + 1
            : 1;

        return sprintf('%s/%d/%04d', $prefix, $year, $sequence);
    }

    /**
     * Assigne une référence à un courrier qui n'en a pas encore.
     */
    public function assign(Correspondence $correspondence): Correspondence
    {
        if (blank($correspondence->reference)) {
            $correspondence->reference = $this->next(
                $correspondence->type,
                $correspondence->correspondence_date?->year,
            );
        }

        return $correspondence;
    }
}
