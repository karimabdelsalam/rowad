<?php

namespace App\Support\Translation;

use Illuminate\Translation\Translator as BaseTranslator;

/**
 * Arabic is the source language: the Arabic text is the key and there is no ar.json.
 * Laravel picks the fallback locale for trans_choice() when the key is missing from the current
 * locale's files, which turned every Arabic plural into English once en.json had it.
 */
class Translator extends BaseTranslator
{
    public const SOURCE_LOCALE = 'ar';

    protected function localeForChoice($key, $locale)
    {
        $locale = $locale ?: $this->locale;

        return $locale === self::SOURCE_LOCALE ? $locale : parent::localeForChoice($key, $locale);
    }
}
