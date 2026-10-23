<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum InstrumentCondition: string
{
    case Excellent = 'excellent';
    case Bon = 'bon';
    case Moyen = 'moyen';
    case HorsService = 'hors_service';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Excellent->value => 'Excellent',
            self::Bon->value => 'Bon',
            self::Moyen->value => 'Moyen',
            self::HorsService->value => 'Hors service',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Excellent->value => 'green',
            self::Bon->value => 'emerald',
            self::Moyen->value => 'amber',
            self::HorsService->value => 'red',
        ];
    }
}
