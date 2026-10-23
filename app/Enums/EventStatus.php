<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * Cycle de vie d'un événement liturgique (répétition, célébration, concert).
 */
enum EventStatus: string
{
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';
    case Reportee = 'reportee';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Planifiee->value => 'Planifiée',
            self::EnCours->value => 'En cours',
            self::Terminee->value => 'Terminée',
            self::Annulee->value => 'Annulée',
            self::Reportee->value => 'Reportée',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Planifiee->value => 'blue',
            self::EnCours->value => 'amber',
            self::Terminee->value => 'green',
            self::Annulee->value => 'red',
            self::Reportee->value => 'orange',
        ];
    }
}
