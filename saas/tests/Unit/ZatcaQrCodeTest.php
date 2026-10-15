<?php

namespace Tests\Unit;

use App\Services\Zatca\ZatcaQrCode;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ZatcaQrCodeTest extends TestCase
{
    public function test_encodes_the_five_tlv_fields(): void
    {
        $payload = ZatcaQrCode::payload('مكتب رواد العقاري', '310122393500003', new DateTimeImmutable('2026-10-15 09:30:00'), 1150, 150);

        $this->assertSame([
            1 => 'مكتب رواد العقاري',
            2 => '310122393500003',
            3 => '2026-10-15T09:30:00Z',
            4 => '1150.00',
            5 => '150.00',
        ], ZatcaQrCode::decode($payload));
    }

    public function test_tlv_length_is_in_bytes_for_arabic_names(): void
    {
        $raw = base64_decode(ZatcaQrCode::payload('رواد', '310122393500003', new DateTimeImmutable(), 1, 0));
        $this->assertSame(1, ord($raw[0]));
        $this->assertSame(strlen('رواد'), ord($raw[1])); // 8 bytes, not 4 characters
    }

    public function test_rejects_invalid_vat_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ZatcaQrCode::payload('x', '123', new DateTimeImmutable(), 1, 0);
    }
}
