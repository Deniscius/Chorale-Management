<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum ParticipationStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excuse = 'excuse';
    case Invite = 'invite';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Present->value => 'Présent',
            self::Late->value => 'En retard',
            self::Absent->value => 'Absent',
            self::Excuse->value => 'Excusé',
            self::Invite->value => 'Invité',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Present->value => 'green',
            self::Late->value => 'amber',
            self::Absent->value => 'red',
            self::Excuse->value => 'orange',
            self::Invite->value => 'slate',
        ];
    }
}
