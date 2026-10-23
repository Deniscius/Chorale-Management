<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * Postes budgétaires de la chorale.
 *
 * Les valeurs correspondent exactement aux libellés enregistrés en base
 * (colonne `budgets.category`).
 */
enum BudgetCategory: string
{
    case Fonctionnement = 'Fonctionnement';
    case Partitions = 'Partitions';
    case Instruments = 'Instruments';
    case Formation = 'Formation';
    case Sorties = 'Sorties';
    case Concerts = 'Concerts';
    case Social = 'Social';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Fonctionnement->value => 'Fonctionnement',
            self::Partitions->value => 'Partitions et ouvrages',
            self::Instruments->value => 'Instruments et maintenance',
            self::Formation->value => 'Formation musicale',
            self::Sorties->value => 'Sorties et voyages',
            self::Concerts->value => 'Concerts et célébrations',
            self::Social->value => 'Actions sociales',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Fonctionnement->value => 'slate',
            self::Partitions->value => 'indigo',
            self::Instruments->value => 'orange',
            self::Formation->value => 'blue',
            self::Sorties->value => 'teal',
            self::Concerts->value => 'purple',
            self::Social->value => 'emerald',
        ];
    }
}
