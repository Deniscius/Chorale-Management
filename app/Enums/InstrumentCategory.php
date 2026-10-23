<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum InstrumentCategory: string
{
    case Orgue = 'orgue';
    case Piano = 'piano';
    case Synthese = 'synthese';
    case Percussions = 'percussions';
    case Tastrophes = 'tastrophes';
    case Cordes = 'cordes';
    case Sonorisation = 'sonorisation';
    case Video = 'video';
    case Autre = 'autre';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Orgue->value => 'Orgue',
            self::Piano->value => 'Piano',
            self::Synthese->value => 'Synthétiseur',
            self::Percussions->value => 'Percussions',
            self::Tastrophes->value => 'Tastrophes',
            self::Cordes->value => 'Cordes',
            self::Sonorisation->value => 'Sonorisation',
            self::Video->value => 'Vidéoprojection',
            self::Autre->value => 'Autre',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Orgue->value => 'red',
            self::Piano->value => 'indigo',
            self::Synthese->value => 'purple',
            self::Percussions->value => 'orange',
            self::Tastrophes->value => 'amber',
            self::Cordes->value => 'blue',
            self::Sonorisation->value => 'teal',
            self::Video->value => 'slate',
            self::Autre->value => 'gray',
        ];
    }
}
