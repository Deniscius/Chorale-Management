<?php

namespace App\Services;

final class SubscriptionPlan
{
    public const MONTHLY_AMOUNT = 2000;

    public const CURRENCY = 'XOF';

    public static function price(): string
    {
        return number_format(self::MONTHLY_AMOUNT, 0, ',', ' ').' FCFA';
    }

    public static function matches(array $license): bool
    {
        $value = data_get($license, 'product.pricing.price.value');

        return (is_int($value) || is_float($value) || is_string($value))
            && preg_match('/^'.self::MONTHLY_AMOUNT.'(?:\.0+)?$/D', (string) $value)
            && data_get($license, 'product.pricing.price.currency') === self::CURRENCY;
    }
}
