<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * État d'un rappel dans le journal d'envoi.
 */
enum ReminderStatus: string
{
    case EnAttente = 'en_attente';
    case Envoye = 'envoye';
    case Echec = 'echec';
    case Ignore = 'ignore';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::EnAttente->value => 'En attente',
            self::Envoye->value => 'Envoyé',
            self::Echec->value => 'Échec',
            self::Ignore->value => 'Ignoré',
        ];
    }

    public static function colors(): array
    {
        return [
            self::EnAttente->value => 'amber',
            self::Envoye->value => 'green',
            self::Echec->value => 'red',
            self::Ignore->value => 'slate',
        ];
    }
}
