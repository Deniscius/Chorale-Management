<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum CorrespondenceType: string
{
    case Entrant = 'entrant';
    case Sortant = 'sortant';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Entrant->value => 'Courrier entrant',
            self::Sortant->value => 'Courrier sortant',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Entrant->value => 'sky',
            self::Sortant->value => 'violet',
        ];
    }

    /**
     * Préfixe du chrono automatique : CE = Courrier Entrant, CS = Courrier Sortant.
     */
    public function referencePrefix(): string
    {
        return $this === self::Entrant ? 'CE' : 'CS';
    }
}
