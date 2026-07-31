<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * التحقق من TOTP ضد متجهات الاختبار الرسمية في RFC 6238.
 *
 * هذه ليست اختبارات لسلوكنا نحن بل لمطابقة المعيار: لو خالفناه فلن تقبل تطبيقات
 * المصادقة رموزنا، ولن يتمكن أحد من الدخول.
 */
class TotpServiceTest extends TestCase
{
    private TotpService $totp;

    /** السر في المعيار هو ASCII "12345678901234567890" مُرمَّزًا Base32 */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new TotpService();
    }

    public static function rfcVectors(): array
    {
        return [
            't=59'         => [59, '287082'],
            't=1111111109' => [1111111109, '081804'],
            't=1111111111' => [1111111111, '050471'],
            't=1234567890' => [1234567890, '005924'],
            't=2000000000' => [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function test_matches_rfc6238_vectors(int $time, string $expected): void
    {
        $this->assertSame($expected, $this->totp->code(self::RFC_SECRET, $time));
    }

    public function test_accepts_current_code(): void
    {
        $this->assertTrue($this->totp->verify(self::RFC_SECRET, $this->totp->code(self::RFC_SECRET)));
    }

    public function test_tolerates_one_period_of_clock_drift(): void
    {
        foreach ([-30, 30] as $drift) {
            $code = $this->totp->code(self::RFC_SECRET, time() + $drift);
            $this->assertTrue($this->totp->verify(self::RFC_SECRET, $code), "انحراف $drift ثانية");
        }
    }

    public function test_rejects_code_outside_the_window(): void
    {
        $stale = $this->totp->code(self::RFC_SECRET, time() - 300);
        $this->assertFalse($this->totp->verify(self::RFC_SECRET, $stale));
    }

    public function test_rejects_malformed_input(): void
    {
        foreach (['', '123', '1234567', 'abcdef', '12 34 56'] as $bad) {
            $this->assertFalse($this->totp->verify(self::RFC_SECRET, $bad), "المدخل: '$bad'");
        }
    }

    public function test_rejects_code_from_a_different_secret(): void
    {
        $other = $this->totp->newSecret();
        $this->assertFalse($this->totp->verify(self::RFC_SECRET, $this->totp->code($other)));
    }

    public function test_new_secret_is_valid_base32_of_expected_length(): void
    {
        $s = $this->totp->newSecret();
        $this->assertSame(32, strlen($s));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $s);
        // ومفتاح جديد يجب أن ينتج رمزًا صالحًا يقبله التحقق
        $this->assertTrue($this->totp->verify($s, $this->totp->code($s)));
    }

    public function test_uri_carries_the_secret_and_issuer(): void
    {
        $uri = $this->totp->uri('ABC234', 'karim', 'Planova');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=ABC234', $uri);
        $this->assertStringContainsString('issuer=Planova', $uri);
    }
}
