<?php

use App\Services\SettingsService;

if (!function_exists('settings')) {
    /** قيمة إعداد من جدول الإعدادات */
    function settings(string $key, string $default = ''): string
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (!function_exists('money')) {
    /** مبلغ منسَّق بعملة الكونسول */
    function money(mixed $amount): string
    {
        return number_format((float)$amount, 2) . ' ' . settings('currency', 'ج.م');
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(mixed $d): string
    {
        if (!$d) {
            return '—';
        }

        return $d instanceof DateTimeInterface
            ? $d->format('Y/m/d')
            : date('Y/m/d', strtotime((string)$d));
    }
}
