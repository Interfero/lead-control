<?php

namespace Tests\Unit;

use App\Support\Totp;
use Tests\TestCase;

class TotpTest extends TestCase
{
    public function test_generate_and_verify(): void
    {
        $secret = Totp::generateSecret();
        $this->assertSame(16, strlen($secret));
        $code = Totp::at($secret, (int) floor(time() / 30));
        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }
}
