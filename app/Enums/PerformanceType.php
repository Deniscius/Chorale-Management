<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum PerformanceType: string
{
    case Concert = 'concert';
    case Messe = 'messe';
    case Mariage = 'mariage';
    case Funerailles = 'funerailles';
    case FêtePatronale = 'fete_patronale';
    case Retraite = 'retraite';
    case Festival = 'festival';
    case Ememoration = 'ememoration';
    case Ordination = 'ordination';
    case Veille = 'veille';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Concert->value => 'Concert',
            self::Messe->value => 'Messe',
            self::Mariage->value => 'Mariage',
            self::Funerailles->value => 'Funérailles',
            self::FêtePatronale->value => 'Fête patronale',
            self::Retraite->value => 'Retraite spirituelle',
            self::Festival->value => 'Festival',
            self::Ememoration->value => 'Célébration commémorative',
            self::Ordination->value => 'Ordination',
            self::Veille->value => 'Veillée',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Concert->value => 'blue',
            self::Messe->value => 'red',
            self::Mariage->value => 'pink',
            self::Funerailles->value => 'slate',
            self::FêtePatronale->value => 'amber',
            self::Retraite->value => 'indigo',
            self::Festival->value => 'purple',
            self::Ememoration->value => 'teal',
            self::Ordination->value => 'violet',
            self::Veille->value => 'gray',
        ];
    }
}
