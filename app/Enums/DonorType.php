<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum DonorType: string
{
    case Choriste = 'choriste';
    case Paroissien = 'paroissien';
    case Externe = 'externe';
    case Anonyme = 'anonyme';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Choriste->value => 'Choriste',
            self::Paroissien->value => 'Paroissien',
            self::Externe->value => 'Donateur externe',
            self::Anonyme->value => 'Don anonyme',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Choriste->value => 'blue',
            self::Paroissien->value => 'emerald',
            self::Externe->value => 'violet',
            self::Anonyme->value => 'gray',
        ];
    }
}
