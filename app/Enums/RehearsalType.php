<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * Nature d'une répétition.
 */
enum RehearsalType: string
{
    case Repetition = 'repetition';
    case Formation = 'formation';
    case Reunion = 'reunion';
    case Concert = 'concert';
    case Veille = 'veille';
    case Generale = 'generale';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Repetition->value => 'Répétition',
            self::Formation->value => 'Formation musicale',
            self::Reunion->value => 'Réunion',
            self::Concert->value => 'Concert',
            self::Veille->value => 'Veillée',
            self::Generale->value => 'Répétition générale',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Repetition->value => 'blue',
            self::Formation->value => 'indigo',
            self::Reunion->value => 'slate',
            self::Concert->value => 'purple',
            self::Veille->value => 'teal',
            self::Generale->value => 'amber',
        ];
    }
}
