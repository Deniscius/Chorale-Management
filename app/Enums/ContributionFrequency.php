<?php

namespace App\Enums;

enum ContributionFrequency: int
{
    case Ponctuelle = 0;
    case Mensuelle = 1;
    case Trimestrielle = 3;
    case Semestrielle = 6;
    case Annuelle = 12;

    public function label(): string
    {
        return match ($this) {
            self::Ponctuelle => 'Ponctuelle (saisie manuelle)',
            self::Mensuelle => 'Mensuelle',
            self::Trimestrielle => 'Trimestrielle',
            self::Semestrielle => 'Semestrielle',
            self::Annuelle => 'Annuelle',
        };
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $frequency) => [$frequency->value => $frequency->label()])->all();
    }
}
