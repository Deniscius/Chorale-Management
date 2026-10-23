<?php

namespace App\Support;

/**
 * Formatage monétaire de la chorale (franc CFA).
 */
final class Money
{
    public const CURRENCY = 'FCFA';

    /**
     * Formate un montant avec séparateurs de milliers.
     */
    public static function format(float|int|string|null $amount, bool $withCurrency = true): string
    {
        $value = (float) ($amount ?? 0);

        $formatted = number_format($value, 0, ',', ' ');

        return $withCurrency ? $formatted.' '.self::CURRENCY : $formatted;
    }

    /**
     * Version compacte pour les tuiles d'indicateurs (1,2 M).
     */
    public static function compact(float|int|string|null $amount, bool $withCurrency = true): string
    {
        $value = (float) ($amount ?? 0);
        $absolute = abs($value);

        $formatted = match (true) {
            $absolute >= 1_000_000_000 => self::trimTrailingZero(number_format($value / 1_000_000_000, 1, ',', ' ')).' Md',
            $absolute >= 1_000_000 => self::trimTrailingZero(number_format($value / 1_000_000, 1, ',', ' ')).' M',
            $absolute >= 10_000 => self::trimTrailingZero(number_format($value / 1_000, 1, ',', ' ')).' k',
            default => number_format($value, 0, ',', ' '),
        };

        return $withCurrency ? $formatted.' '.self::CURRENCY : $formatted;
    }

    /**
     * Supprime la décimale inutile d'un nombre compacté.
     */
    private static function trimTrailingZero(string $value): string
    {
        return str_contains($value, ',') ? rtrim(rtrim($value, '0'), ',') : $value;
    }

    /**
     * Montant signé, utilisé dans les listes d'écritures.
     */
    public static function signed(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        return ($value < 0 ? '−' : '+').' '.self::format(abs($value));
    }
}
