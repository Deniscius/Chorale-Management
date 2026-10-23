<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PaymentMethod: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Autre = 'autre';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Especes->value => 'Espèces',
            self::MobileMoney->value => 'Mobile Money',
            self::Virement->value => 'Virement bancaire',
            self::Cheque->value => 'Chèque',
            self::Autre->value => 'Autre',
        ];
    }
}
