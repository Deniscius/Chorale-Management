<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum MovementType: string
{
    case Sortie = 'sortie';
    case Retour = 'retour';
    case Pret = 'pret';
    case Transfert = 'transfert';
    case Maintenance = 'maintenance';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Sortie->value => 'Sortie',
            self::Retour->value => 'Retour',
            self::Pret->value => 'Prêt',
            self::Transfert->value => 'Transfert interne',
            self::Maintenance->value => 'Mise en maintenance',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Sortie->value => 'blue',
            self::Retour->value => 'green',
            self::Pret->value => 'amber',
            self::Transfert->value => 'slate',
            self::Maintenance->value => 'orange',
        ];
    }
}
