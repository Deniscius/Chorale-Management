<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum AccountType: string
{
    case Tresorerie = 'tresorerie';
    case Actif = 'actif';
    case Passif = 'passif';
    case Produit = 'produit';
    case Charge = 'charge';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Tresorerie->value => 'Trésorerie',
            self::Actif->value => 'Actif',
            self::Passif->value => 'Passif',
            self::Produit->value => 'Produit',
            self::Charge->value => 'Charge',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Tresorerie->value => 'emerald',
            self::Actif->value => 'blue',
            self::Passif->value => 'violet',
            self::Produit->value => 'green',
            self::Charge->value => 'red',
        ];
    }
}
