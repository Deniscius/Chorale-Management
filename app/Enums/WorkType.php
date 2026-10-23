<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum WorkType: string
{
    case Messe = 'messe';
    case MessePropre = 'messe_propre';
    case Motet = 'motet';
    case Cantique = 'cantique';
    case Psaume = 'psaume';
    case Hymne = 'hymne';
    case Sequence = 'sequence';
    case Chant = 'chant';
    case Benedicite = 'benedicite';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Messe->value => 'Messe',
            self::MessePropre->value => 'Messe propre',
            self::Motet->value => 'Motet',
            self::Cantique->value => 'Cantique',
            self::Psaume->value => 'Psaume',
            self::Hymne->value => 'Hymne',
            self::Sequence->value => 'Séquence',
            self::Chant->value => 'Chant',
            self::Benedicite->value => 'Bénédicite',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Messe->value => 'red',
            self::MessePropre->value => 'rose',
            self::Motet->value => 'purple',
            self::Cantique->value => 'blue',
            self::Psaume->value => 'indigo',
            self::Hymne->value => 'amber',
            self::Sequence->value => 'orange',
            self::Chant->value => 'teal',
            self::Benedicite->value => 'lime',
        ];
    }
}
