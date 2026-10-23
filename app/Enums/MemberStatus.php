<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum MemberStatus: string
{
    case Actif = 'actif';
    case Inactif = 'inactif';
    case Invite = 'invite';
    case Honoraire = 'honoraire';
    case Externe = 'externe';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Actif->value => 'Actif',
            self::Inactif->value => 'Inactif',
            self::Invite->value => 'Invité',
            self::Honoraire->value => 'Honoraire',
            self::Externe->value => 'Intervenant extérieur',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Actif->value => 'green',
            self::Inactif->value => 'gray',
            self::Invite->value => 'amber',
            self::Honoraire->value => 'indigo',
            self::Externe->value => 'sky',
        ];
    }
}
