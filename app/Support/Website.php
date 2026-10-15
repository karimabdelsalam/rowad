<?php

namespace App\Support;

/** The office's public landing page (plan feature + office setting). */
final class Website
{
    public static function enabled(): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }
        $plan = tenant()->plan;

        return ($plan === null || $plan->hasFeature('landing_page')) && Settings::enabled('landing_enabled');
    }

    /** Office setting in the current language with Arabic fallback, e.g. text('landing_hero_title'). */
    public static function text(string $key, ?string $default = null): ?string
    {
        if (app()->getLocale() !== 'ar' && filled($english = Settings::get($key.'_en'))) {
            return $english;
        }

        return Settings::get($key, $default);
    }

    public static function name(): string
    {
        return (string) (self::text('site_name') ?: tenant('name'));
    }
}
