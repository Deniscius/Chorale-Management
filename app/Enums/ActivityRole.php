<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * Rôle tenu par un choriste dans une activité (répétition, concert, sortie…).
 */
enum ActivityRole: string
{
    case Choriste = 'choriste';
    case Soliste = 'soliste';
    case ChefDePupitre = 'chef_pupitre';
    case Instrumentiste = 'instrumentiste';
    case Benevole = 'benevole';
    case Encadrant = 'encadrant';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::Choriste->value => 'Choriste',
            self::Soliste->value => 'Soliste',
            self::ChefDePupitre->value => 'Chef de pupitre',
            self::Instrumentiste->value => 'Instrumentiste',
            self::Benevole->value => 'Bénévole',
            self::Encadrant->value => 'Encadrant',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Choriste->value => 'blue',
            self::Soliste->value => 'purple',
            self::ChefDePupitre->value => 'indigo',
            self::Instrumentiste->value => 'orange',
            self::Benevole->value => 'emerald',
            self::Encadrant->value => 'slate',
        ];
    }
}
