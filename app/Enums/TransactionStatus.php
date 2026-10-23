<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum TransactionStatus: string
{
    case Brouillon = 'brouillon';
    case Validee = 'validee';
    case Rejetee = 'rejetee';
    case Cloturee = 'cloturee';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Brouillon->value => 'Brouillon',
            self::Validee->value => 'Validée',
            self::Rejetee->value => 'Rejetée',
            self::Cloturee->value => 'Clôturée',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Brouillon->value => 'gray',
            self::Validee->value => 'green',
            self::Rejetee->value => 'red',
            self::Cloturee->value => 'slate',
        ];
    }
}
