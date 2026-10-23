<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum Difficulty: int
{
    case TresFacile = 1;
    case Facile = 2;
    case Moyen = 3;
    case Avance = 4;
    case Expert = 5;

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::TresFacile->value => 'Très facile',
            self::Facile->value => 'Facile',
            self::Moyen->value => 'Moyen',
            self::Avance->value => 'Avancé',
            self::Expert->value => 'Expert',
        ];
    }

    public static function colors(): array
    {
        return [
            self::TresFacile->value => 'green',
            self::Facile->value => 'emerald',
            self::Moyen->value => 'amber',
            self::Avance->value => 'orange',
            self::Expert->value => 'red',
        ];
    }

    public function stars(): string
    {
        return str_repeat('★', $this->value).str_repeat('☆', 5 - $this->value);
    }
}
