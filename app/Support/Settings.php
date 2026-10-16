<?php

namespace App\Support;

use App\Models\Setting;

/** Per-tenant key/value settings (legacy `setting` table), memoised per request. */
final class Settings
{
    /** Stored encrypted at rest. */
    public const SECRET_KEYS = ['smtp_password', 'sms_password', 'moyasar_secret_key', 'moyasar_webhook_secret'];

    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        $value = array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;

        if ($value !== $default && in_array($key, self::SECRET_KEYS, true) && is_string($value)) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                return $default;
            }
        }

        return $value;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        if (in_array($key, self::SECRET_KEYS, true) && filled($value)) {
            $value = \Illuminate\Support\Facades\Crypt::encryptString((string) $value);
        }
        if (is_array($value)) { // lists (e.g. the payment methods an office turned on) are stored as JSON
            $value = json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }
        $setting = Setting::firstOrNew(['key' => $key], ['group' => $group]);
        $setting->value = $value;
        $setting->save();
        self::flush();
    }

    /** @return list<mixed> a list stored by set(), or the default when unset */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);
        if (is_array($value)) {
            return array_values($value);
        }
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? array_values($decoded) : $default;
    }

    public static function enabled(string $key): bool
    {
        return filter_var(self::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key, 0);
    }

    /** The office currency as shown in the UI: Saudi riyal reads "ر.س" in Arabic and "SAR" in English, whatever way it was typed. */
    public static function currencyLabel(): string
    {
        $currency = trim((string) self::get('currency', 'SAR'));
        if (in_array(mb_strtolower($currency), ['sar', 'ر.س', 'ر.س.', 'ريال', 'ريال سعودي', 'رس'], true)) {
            return app()->getLocale() === 'ar' ? 'ر.س' : 'SAR';
        }

        return $currency;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$cache[self::scope()] ??= Setting::query()->pluck('value', 'key')->all();
    }

    public static function flush(): void
    {
        unset(self::$cache[self::scope()]);
    }

    /** Called when an organisation's context starts, so long-running queue workers never use stale settings. */
    public static function forgetAll(): void
    {
        self::$cache = [];
    }

    private static function scope(): string
    {
        return tenancy()->initialized ? (string) tenant()->getTenantKey() : 'central';
    }
}
