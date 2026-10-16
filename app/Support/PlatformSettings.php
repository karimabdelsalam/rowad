<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Setting;
use App\Services\Platform\OfficeProvisioner;

/**
 * Settings of the platform itself (signup, subdomains, contact, announcements), kept in the central
 * database so they can be changed from the platform console without touching .env. Every value has a
 * default, so a fresh install behaves exactly as before the table existed.
 */
final class PlatformSettings
{
    public const DEFAULTS = [
        'signup_open' => true,
        'signup_closed_message' => '',
        'default_plan_id' => null,
        'trial_days' => null,          // null: each plan's own trial length
        'subdomain_min_length' => 3,
        'reserved_subdomains' => [],   // on top of OfficeProvisioner::RESERVED_SUBDOMAINS
        'custom_domains_enabled' => true,
        'contact_email' => null,       // null: PLATFORM_CONTACT_* from .env
        'contact_phone' => null,
        'contact_whatsapp' => null,
        'announcement_enabled' => false,
        'announcement_level' => 'info',
        'announcement_text' => '',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : (self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string, mixed> every stored value (defaults are not included) */
    public static function all(): array
    {
        return self::$cache ??= self::query()->pluck('value', 'key')->all();
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $setting = self::query()->firstOrNew(['key' => $key], ['group' => 'platform']);
            $setting->value = $value === '' ? null : $value;
            $setting->save();
        }
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function signupOpen(): bool
    {
        return filter_var(self::get('signup_open'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function defaultPlan(): ?Plan
    {
        $id = self::get('default_plan_id');

        return ($id ? Plan::find($id) : null) ?? Plan::where('is_active', true)->where('slug', 'professional')->first();
    }

    public static function trialDays(?Plan $plan): int
    {
        $days = self::get('trial_days');

        return $days !== null && $days !== '' ? max(0, (int) $days) : (int) ($plan?->trial_days ?? 14);
    }

    /** @return list<string> */
    public static function reservedSubdomains(): array
    {
        $extra = array_map(fn ($s) => strtolower(trim((string) $s)), (array) self::get('reserved_subdomains'));

        return array_values(array_unique(array_merge(OfficeProvisioner::RESERVED_SUBDOMAINS, array_filter($extra))));
    }

    public static function subdomainMinLength(): int
    {
        return max(2, min(20, (int) self::get('subdomain_min_length')));
    }

    /** Contact details for the public pages: the console value, else the .env value. */
    public static function contact(string $field): ?string
    {
        $value = self::get('contact_'.$field);

        return filled($value) ? (string) $value : (config('rowad.contact.'.$field) ?: null);
    }

    /** @return array{level: string, text: string}|null the banner shown at the top of every office panel */
    public static function announcement(): ?array
    {
        if (! filter_var(self::get('announcement_enabled'), FILTER_VALIDATE_BOOLEAN) || blank(self::get('announcement_text'))) {
            return null;
        }

        return ['level' => in_array(self::get('announcement_level'), ['info', 'warning', 'danger'], true) ? self::get('announcement_level') : 'info',
            'text' => (string) self::get('announcement_text')];
    }

    /** Always the central database, whichever office's context the request runs in. */
    private static function query(): \Illuminate\Database\Eloquent\Builder
    {
        return Setting::on(config('tenancy.database.central_connection'))->where('group', 'platform');
    }
}
