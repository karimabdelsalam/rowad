<?php

namespace App\Support;

use App\Models\Setting;

/** Per-tenant key/value settings (legacy `setting` table), memoised per request. */
final class Settings
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
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

    private static function scope(): string
    {
        return tenancy()->initialized ? (string) tenant()->getTenantKey() : 'central';
    }
}
