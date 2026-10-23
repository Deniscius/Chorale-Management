<?php

namespace App\Services;

use App\Enums\CorrespondenceType;
use App\Models\Correspondence;

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
        return app(ReferenceSequence::class)->next('correspondences', $type->referencePrefix(), $year ?? now()->year);
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
