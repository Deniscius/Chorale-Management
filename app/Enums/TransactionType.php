<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum TransactionType: string
{
    case Recette = 'recette';
    case Depense = 'depense';
    case Transfert = 'transfert';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Recette->value => 'Recette',
            self::Depense->value => 'Dépense',
            self::Transfert->value => 'Transfert',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Recette->value => 'green',
            self::Depense->value => 'red',
            self::Transfert->value => 'blue',
        ];
    }
}
