<?php

namespace App\Support;

use NumberFormatter;

/** Legacy I18N_Arabic int2str usage on vouchers/contracts: "فقط ألف ومائتان ريال لا غير". */
final class AmountInWords
{
    /** Bare words as legacy int2str printed them ("مئة وعشرة ألف"), for office templates that add their own wording. */
    public static function words(float $amount): string
    {
        return (new \ArPHP\I18N\Arabic)->int2str(round($amount, 2));
    }

    public static function format(float $amount, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $speller = new NumberFormatter($locale === 'ar' ? 'ar' : 'en', NumberFormatter::SPELLOUT);
        $whole = (int) floor(round($amount, 2));
        $fraction = (int) round(($amount - $whole) * 100);

        if ($locale === 'ar') {
            // ar-php (successor of the legacy I18N_Arabic library) has correct Arabic number grammar; ICU's does not.
            return 'فقط '.(new \ArPHP\I18N\Arabic)->money2str(round($amount, 2), 'SAR', 'ar').' لا غير';
        }

        $text = ucfirst($speller->format($whole)).' Saudi riyals';
        if ($fraction > 0) {
            $text .= ' and '.$speller->format($fraction).' halalas';
        }

        return $text.' only';
    }
}
