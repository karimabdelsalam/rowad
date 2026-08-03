<?php
declare(strict_types=1);

/**
 * التحقق بخطوتين TOTP (RFC 6238) بلا أي مكتبات خارجية.
 *
 * يعمل مع Google Authenticator وMicrosoft Authenticator وAuthy وأشباهها.
 * التطبيق يُدخل له المفتاح يدويًا أو برابط otpauth — لا حاجة لصورة QR من خادم
 * خارجي (الـ CSP عندنا يمنعها أصلًا، والمفتاح النصي يعمل في كل التطبيقات).
 */

/** مفتاح جديد بصيغة Base32 (160 بت كما توصي RFC 4226) */
function totp_new_secret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    for ($i = 0; $i < 32; $i++) {
        $s .= $alphabet[random_int(0, 31)];
    }
    return $s;
}

/** فك Base32 (بدون حشو) إلى بايتات خام */
function base32_decode_raw(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(rtrim($b32, '='));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $v = strpos($alphabet, $c);
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

/** رمز TOTP لست خانات لنافذة زمنية بعينها */
function totp_code(string $secret, ?int $forTime = null): string
{
    $key = base32_decode_raw($secret);
    $counter = (int)floor(($forTime ?? time()) / 30);
    $binCounter = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $num = ((ord($hash[$offset]) & 0x7F) << 24)
         | (ord($hash[$offset + 1]) << 16)
         | (ord($hash[$offset + 2]) << 8)
         | ord($hash[$offset + 3]);
    return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * يتحقق من رمز المستخدم بنافذة ±1 (30 ثانية قبل وبعد) لتفاوت ساعات الهواتف.
 */
function totp_verify(string $secret, string $input): bool
{
    $input = preg_replace('/\D/', '', $input) ?? '';
    if (strlen($input) !== 6 || $secret === '') {
        return false;
    }
    $now = time();
    foreach ([-30, 0, 30] as $drift) {
        if (hash_equals(totp_code($secret, $now + $drift), $input)) {
            return true;
        }
    }
    return false;
}

/** رابط otpauth يفتح مباشرة في تطبيقات المصادقة */
function totp_uri(string $secret, string $account, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
