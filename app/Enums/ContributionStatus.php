<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum ContributionStatus: string
{
    case Due = 'due';
    case Payee = 'payee';
    case Partiel = 'partiel';
    case Retard = 'retard';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Due->value => 'À recouvrer',
            self::Payee->value => 'Payée',
            self::Partiel->value => 'Partiellement payée',
            self::Retard->value => 'En retard',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Due->value => 'amber',
            self::Payee->value => 'green',
            self::Partiel->value => 'blue',
            self::Retard->value => 'red',
        ];
    }
}
