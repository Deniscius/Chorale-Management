<?php

namespace Tests\Unit;

use App\Services\Platform\Totp;
use PHPUnit\Framework\TestCase;

class PlatformTotpTest extends TestCase
{
    public function test_sha1_codes_match_all_rfc_6238_vectors(): void
    {
        $totp = new Totp;
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $timestamp => $expected) {
            $this->assertSame($expected, $totp->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv($timestamp, 30), 8));
        }
    }
}
