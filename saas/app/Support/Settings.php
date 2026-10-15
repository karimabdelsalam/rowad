<?php

namespace App\Support;

use App\Models\Setting;

/** Per-tenant key/value settings (legacy `setting` table), memoised per request. */
final class Settings
{
    /** Stored encrypted at rest. */
    public const SECRET_KEYS = ['smtp_password', 'sms_password'];

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
        $setting = Setting::firstOrNew(['key' => $key], ['group' => $group]);
        $setting->value = $value;
        $setting->save();
        self::flush();
    }

    public static function enabled(string $key): bool
    {
        return filter_var(self::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key, 0);
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
