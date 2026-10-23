<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum MaintenanceType: string
{
    case Accord = 'accord';
    case Reparation = 'reparation';
    case Entretien = 'entretien';
    case Reglage = 'reglage';
    case Remplacement = 'remplacement';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Accord->value => 'Accord / réglage',
            self::Reparation->value => 'Réparation',
            self::Entretien->value => 'Entretien',
            self::Reglage->value => 'Réglage technique',
            self::Remplacement->value => 'Remplacement de pièce',
        ];
    }
}
