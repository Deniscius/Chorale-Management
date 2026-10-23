<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CorrespondenceChannel: string
{
    case Courrier = 'courrier';
    case Recommande = 'recommande';
    case Email = 'email';
    case MainPropre = 'main_propre';
    case Telephone = 'telephone';
    case Verbal = 'verbal';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Courrier->value => 'Courrier postal',
            self::Recommande->value => 'Recommandé',
            self::Email->value => 'E-mail',
            self::MainPropre->value => 'Remis en main propre',
            self::Telephone->value => 'Téléphone',
            self::Verbal->value => 'Transmission verbale',
        ];
    }
}
