<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Confidentiality: string
{
    case Normal = 'normal';
    case Confidentiel = 'confidentiel';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Normal->value => 'Normal',
            self::Confidentiel->value => 'Confidentiel',
        ];
    }
}
