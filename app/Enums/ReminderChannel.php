<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Canal de diffusion d'un rappel.
 */
enum ReminderChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';

    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Mail->value => 'E-mail',
            self::Sms->value => 'SMS',
        ];
    }
}
