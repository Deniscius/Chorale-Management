<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum ActivityType: string
{
    case Repetition = 'repetition';
    case Formation = 'formation';
    case Concert = 'concert';
    case ActionSociale = 'action_sociale';
    case Retraite = 'retraite';
    case Sortie = 'sortie';
    case Reunion = 'reunion';
    case Campagne = 'campagne';
    case Catechese = 'catechese';
    case Autre = 'autre';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Repetition->value => 'Répétition',
            self::Formation->value => 'Formation musicale',
            self::Concert->value => 'Concert',
            self::ActionSociale->value => 'Action sociale',
            self::Retraite->value => 'Retraite spirituelle',
            self::Sortie->value => 'Sortie / voyage',
            self::Reunion->value => 'Réunion',
            self::Campagne->value => 'Campagne',
            self::Catechese->value => 'Catéchèse',
            self::Autre->value => 'Autre',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Repetition->value => 'blue',
            self::Formation->value => 'indigo',
            self::Concert->value => 'purple',
            self::ActionSociale->value => 'emerald',
            self::Retraite->value => 'teal',
            self::Sortie->value => 'orange',
            self::Reunion->value => 'slate',
            self::Campagne->value => 'amber',
            self::Catechese->value => 'sky',
            self::Autre->value => 'gray',
        ];
    }
}
