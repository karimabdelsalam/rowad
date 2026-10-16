<?php

namespace App\Support;

/**
 * Saudi IBANs: SA + 2 check digits + 2-digit bank code + 18-digit account (24 characters), validated with the
 * ISO 13616 mod-97 check. The bank code tells which bank the account is in, so staff only type the IBAN.
 */
final class Iban
{
    /** SAMA bank code => [name as seeded in the banks list, SWIFT/BIC]. */
    public const SAUDI_BANKS = [
        '05' => ['مصرف الإنماء', 'INMASARI'],
        '10' => ['البنك الأهلي السعودي', 'NCBKSAJE'],
        '15' => ['بنك البلاد', 'ALBISARI'],
        '20' => ['بنك الرياض', 'RIBLSARI'],
        '30' => ['البنك العربي الوطني', 'ARNBSARI'],
        '40' => ['البنك الأهلي السعودي', 'NCBKSAJE'], // ex-Samba, merged into SNB
        '45' => ['البنك السعودي البريطاني (ساب)', 'SABBSARI'],
        '50' => ['البنك السعودي البريطاني (ساب)', 'SABBSARI'], // ex-Alawwal, merged into SAB
        '55' => ['البنك السعودي الفرنسي', 'BSFRSARI'],
        '60' => ['بنك الجزيرة', 'BJAZSAJE'],
        '65' => ['البنك السعودي للاستثمار', 'SIBCSARI'],
        '80' => ['مصرف الراجحي', 'RJHISARI'],
        '90' => ['بنك الخليج الدولي', 'GULFSARI'],
    ];

    /** Upper case, no spaces, Western digits. */
    public static function normalize(?string $iban): ?string
    {
        $iban = strtr((string) $iban, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $iban = strtoupper((string) preg_replace('/[\s\-]+/u', '', $iban));

        return $iban === '' ? null : $iban;
    }

    public static function valid(?string $iban): bool
    {
        $iban = self::normalize($iban);
        if ($iban === null || ! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }
        if (str_starts_with($iban, 'SA') && ! preg_match('/^SA\d{22}$/', $iban)) {
            return false;
        }
        // move the first four characters to the end, letters become 10..35, the number mod 97 must be 1
        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }
        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }

    /** @return array{0: string, 1: string}|null [bank name, SWIFT] for a Saudi IBAN */
    public static function bank(?string $iban): ?array
    {
        $iban = self::normalize($iban);

        return $iban !== null && str_starts_with($iban, 'SA') ? (self::SAUDI_BANKS[substr($iban, 4, 2)] ?? null) : null;
    }

    /** SA03 8000 0000 6080 1016 7519 */
    public static function format(?string $iban): string
    {
        return trim(chunk_split((string) self::normalize($iban), 4, ' '));
    }

    /** SA•• •••• 7519 — for messages and lists that should not show the whole account. */
    public static function masked(?string $iban): string
    {
        $iban = (string) self::normalize($iban);

        return $iban === '' ? '' : substr($iban, 0, 2).'… '.substr($iban, -4);
    }
}
