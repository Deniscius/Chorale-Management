<?php

namespace App\Support;

/**
 * Formatage des tailles de fichiers.
 */
final class Files
{
    /**
     * Taille lisible (o / Ko / Mo / Go) en français.
     */
    public static function size(int|float|null $bytes): string
    {
        $value = (float) ($bytes ?? 0);

        return match (true) {
            $value >= 1_073_741_824 => number_format($value / 1_073_741_824, 1, ',', ' ').' Go',
            $value >= 1_048_576 => number_format($value / 1_048_576, 1, ',', ' ').' Mo',
            $value >= 1024 => number_format($value / 1024, 0, ',', ' ').' Ko',
            default => number_format($value, 0, ',', ' ').' o',
        };
    }
}
