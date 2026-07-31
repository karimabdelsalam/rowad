<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * التحقق بخطوتين (TOTP — RFC 6238) بلا مكتبات خارجية.
 *
 * مطابق لمتجهات الاختبار الرسمية في RFC 6238، ويعمل مع Google Authenticator
 * وMicrosoft Authenticator وAuthy.
 */
class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** مفتاح جديد بصيغة Base32 (160 بت كما توصي RFC 4226) */
    public function newSecret(): string
    {
        $s = '';
        for ($i = 0; $i < 32; $i++) {
            $s .= self::ALPHABET[random_int(0, 31)];
        }

        return $s;
    }

    /** رمز الفترة الزمنية الحالية أو فترة محددة */
    public function code(string $secret, ?int $at = null): string
    {
        $key = $this->base32Decode($secret);
        $counter = intdiv($at ?? time(), self::PERIOD);
        $bin = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $num = ((ord($hash[$offset]) & 0x7F) << 24)
             | (ord($hash[$offset + 1]) << 16)
             | (ord($hash[$offset + 2]) << 8)
             | ord($hash[$offset + 3]);

        return str_pad((string)($num % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * يتحقق من الرمز بنافذة ±فترة واحدة، مراعاةً لتفاوت ساعات الهواتف.
     * المقارنة بـ hash_equals حتى لا يسرّب الفرق الزمني أي معلومة.
     */
    public function verify(string $secret, string $input): bool
    {
        $input = preg_replace('/\D/', '', $input) ?? '';
        if (strlen($input) !== self::DIGITS || $secret === '') {
            return false;
        }

        $now = time();
        foreach ([-self::PERIOD, 0, self::PERIOD] as $drift) {
            if (hash_equals($this->code($secret, $now + $drift), $input)) {
                return true;
            }
        }

        return false;
    }

    /** رابط otpauth يفتح مباشرة في تطبيقات المصادقة */
    public function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode("$issuer:$account")
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** فك Base32 إلى بايتات خام (بدون حشو) */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v === false) {
                return '';
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int)bindec($byte));
            }
        }

        return $out;
    }
}
