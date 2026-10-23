<?php

namespace App\Support;

/**
 * Libellés français des mois de l'année.
 */
final class Months
{
    /**
     * @var array<int, string>
     */
    public const NAMES = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    /**
     * Libellé court (3 premières lettres) d'un mois.
     */
    public static function short(int $month): string
    {
        $name = self::NAMES[$month] ?? (string) $month;

        return mb_substr($name, 0, 3);
    }

    /**
     * Libellé complet d'un mois.
     */
    public static function label(int $month): string
    {
        return self::NAMES[$month] ?? (string) $month;
    }
}
