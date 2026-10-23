<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ProgramRole: string
{
    case Entree = 'entree';
    case Psaume = 'psaume';
    case Lecture = 'lecture';
    case Offertory = 'offertory';
    case Communion = 'communion';
    case Final = 'final';
    case Sortie = 'sortie';
    case Supplement = 'supplement';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Entree->value => 'Entrée',
            self::Psaume->value => 'Psaume',
            self::Lecture->value => 'Lecture',
            self::Offertory->value => 'Offertoire',
            self::Communion->value => 'Communion',
            self::Final->value => 'Final',
            self::Sortie->value => 'Sortie',
            self::Supplement->value => 'Supplément',
        ];
    }
}
