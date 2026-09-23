<?php

namespace Tests\Unit;

use App\Helpers\PhoneHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneHelperTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneHelper::normalize($input));
    }

    public static function normalizeProvider(): array
    {
        return [
            ['+7 (904) 953-21-39', '9049532139'],
            ['89049532139', '9049532139'],
            ['9049532139', '9049532139'],
            ['', ''],
        ];
    }

    #[DataProvider('toE164Provider')]
    public function test_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneHelper::toE164($input));
    }

    public static function toE164Provider(): array
    {
        return [
            ['+7 (960) 703-20-82', '+79607032082'],
            ['89607032082', '+79607032082'],
            ['9607032082', '+79607032082'],
            ['123', ''],
            ['', ''],
        ];
    }
}
