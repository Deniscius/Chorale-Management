<?php

namespace Tests\Support;

/** Deterministic PNG for testing proportions without GD or an external image service. */
final class ReportHeaderFixture
{
    public static function png(): string
    {
        $width = 840;
        $height = 180;
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\0";
            for ($x = 0; $x < $width; $x++) {
                $circle = ($x - 90) ** 2 + ($y - 80) ** 2 < 52 ** 2;
                $line = $x > 185 && $x < 775 && (($y > 42 && $y < 58) || ($y > 76 && $y < 85) || ($y > 107 && $y < 115));
                $raw .= $circle ? "\x16\x65\x34" : ($line ? "\x1e\x3a\x8a" : "\xff\xff\xff");
            }
        }

        return "\x89PNG\r\n\x1a\n".self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .self::chunk('IDAT', gzcompress($raw)).self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    }
}
