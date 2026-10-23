<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum Voice: string
{
    case Soprano = 'soprano';
    case Alto = 'alto';
    case Contretenor = 'contretenor';
    case Tenor = 'tenor';
    case Bass = 'bass';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Soprano->value => 'Soprano',
            self::Alto->value => 'Alto',
            self::Contretenor->value => 'Contretenor',
            self::Tenor->value => 'Ténor',
            self::Bass->value => 'Basse',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Soprano->value => 'pink',
            self::Alto->value => 'purple',
            self::Contretenor->value => 'rose',
            self::Tenor->value => 'blue',
            self::Bass->value => 'indigo',
        ];
    }

    /**
     * Pupitres (parties) usuels d'un chœur.
     *
     * @return array<int, string>
     */
    public function parts(): array
    {
        return match ($this) {
            self::Soprano => ['S1', 'S2'],
            self::Alto => ['A1', 'A2'],
            self::Contretenor, self::Tenor => ['T'],
            self::Bass => ['B'],
        };
    }

    /**
     * Liste des pupitres (parties) usuels d'un chœur.
     *
     * @return array<int, string>
     */
    public static function allParts(): array
    {
        return ['S1', 'S2', 'A1', 'A2', 'T', 'B'];
    }
}
