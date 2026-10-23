<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum CorrespondenceStatus: string
{
    case Recu = 'recu';
    case EnCours = 'en_cours';
    case Traite = 'traite';
    case Clos = 'clos';
    case Archive = 'archive';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Recu->value => 'Reçu',
            self::EnCours->value => 'En cours de traitement',
            self::Traite->value => 'Traité',
            self::Clos->value => 'Clos',
            self::Archive->value => 'Archivé',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Recu->value => 'blue',
            self::EnCours->value => 'amber',
            self::Traite->value => 'green',
            self::Clos->value => 'slate',
            self::Archive->value => 'gray',
        ];
    }

    /**
     * Statuts « ouverts » : le courrier reste à traiter.
     *
     * @return array<int, string>
     */
    public static function open(): array
    {
        return [self::Recu->value, self::EnCours->value];
    }
}
