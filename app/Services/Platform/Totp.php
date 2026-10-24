<?php

namespace App\Services\Platform;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    // RFC 6238 / HOTP SHA-1, six digits, 30-second step.
    public function code(#[\SensitiveParameter] string $secret, int $counter, int $digits = 6): string
    {
        $bits = '';
        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid Base32 secret.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), $key, true);
        $offset = ord($hash[19]) & 15;
        $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($number % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function counter(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, int $last): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $current = intdiv(now()->timestamp, 30);
        for ($counter = $current - 1; $counter <= $current + 1; $counter++) {
            if ($counter > $last && hash_equals($this->code($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }
}
