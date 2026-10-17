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
        'ai_provider' => null,         // anthropic, openai, gemini; null: AI_PROVIDER from .env
        'ai_api_key' => null,          // Claude: encrypted; null: ANTHROPIC_API_KEY from .env
        'ai_model' => null,            // null: AI_MODEL from .env
        'openai_api_key' => null,      // encrypted; null: OPENAI_API_KEY
        'openai_model' => null,
        'gemini_api_key' => null,      // encrypted; null: GEMINI_API_KEY
        'gemini_model' => null,
        // platform mail server: system emails (signup, welcome, alerts, password resets) and every office
        // without its own SMTP. null: MAIL_* from .env
        'mail_mailer' => null,          // smtp, sendmail, log
        'mail_host' => null,
        'mail_port' => null,
        'mail_encryption' => null,      // tls, ssl, none
        'mail_username' => null,
        'mail_password' => null,        // encrypted
        'mail_from_address' => null,
        'mail_from_name' => null,
        'mail_reply_to' => null,
        'signup_alert_emails' => [],    // new-office alerts; empty: the contact email
        // the official site (aqarnova.com): texts override the built-in ones per language, empty = built-in
        'site_hero_badge_ar' => null, 'site_hero_badge_en' => null,
        'site_hero_title_ar' => null, 'site_hero_title_en' => null,
        'site_hero_highlight_ar' => null, 'site_hero_highlight_en' => null,
        'site_hero_lead_ar' => null, 'site_hero_lead_en' => null,
        'site_footer_about_ar' => null, 'site_footer_about_en' => null,
        'site_seo_title_ar' => null, 'site_seo_title_en' => null,
        'site_seo_description_ar' => null, 'site_seo_description_en' => null,
        'site_seo_keywords' => null,
        'site_og_image' => null,
        'social_x' => null, 'social_instagram' => null, 'social_linkedin' => null, 'social_facebook' => null,
        'social_tiktok' => null, 'social_youtube' => null, 'social_snapchat' => null,
        'analytics_ga4' => null, 'analytics_gtm' => null, 'analytics_meta_pixel' => null,
        'site_head_code' => null,       // verification tags etc., platform admins only
        'widget_enabled' => null,       // null: on when an id is configured
        'widget_src' => null, 'widget_tenant' => null, 'widget_id' => null,
    ];

    /** Social profiles shown in the site footer, in this order. */
    public const SOCIAL = ['x' => 'X (Twitter)', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook',
        'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'snapchat' => 'Snapchat'];

    /** Kept out of the generic form and saved only through their own setters. */
    public const SECRETS = ['ai_api_key', 'openai_api_key', 'gemini_api_key', 'mail_password'];

    /** provider => [stored key setting, stored model setting, .env key config, .env model config] */
    public const AI_PROVIDERS = [
        'anthropic' => ['ai_api_key', 'ai_model', 'rowad.ai.api_key', 'rowad.ai.model'],
        'openai' => ['openai_api_key', 'openai_model', 'rowad.ai.openai_api_key', 'rowad.ai.openai_model'],
        'gemini' => ['gemini_api_key', 'gemini_model', 'rowad.ai.gemini_api_key', 'rowad.ai.gemini_model'],
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
            if (! array_key_exists($key, self::DEFAULTS) || in_array($key, self::SECRETS, true)) {
                continue;
            }
            $setting = self::query()->firstOrNew(['key' => $key], ['group' => 'platform']);
            $setting->value = $value === '' ? null : $value;
            $setting->save();
        }
        self::$cache = null;
    }

    /** @return array<string, mixed> stored values safe to put in a form (secrets left out) */
    public static function editable(): array
    {
        return array_diff_key(self::all(), array_flip(self::SECRETS));
    }

    /** The AI provider in use: anthropic (Claude), openai or gemini. */
    public static function aiProvider(): string
    {
        $provider = (string) (self::get('ai_provider') ?: config('rowad.ai.provider'));

        return array_key_exists($provider, self::AI_PROVIDERS) ? $provider : 'anthropic';
    }

    /** A provider's key: the one saved in the console (encrypted at rest), else the server's (.env). */
    public static function aiApiKey(?string $provider = null): ?string
    {
        [$setting, , $env] = self::AI_PROVIDERS[$provider ?? self::aiProvider()];
        $stored = self::all()[$setting] ?? null;
        if (filled($stored)) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString((string) $stored);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                // APP_KEY changed since it was saved: fall back to .env until it is entered again
            }
        }

        return config($env) ?: null;
    }

    public static function aiModel(?string $provider = null): ?string
    {
        [, $setting, , $env] = self::AI_PROVIDERS[$provider ?? self::aiProvider()];

        return ((string) self::get($setting)) ?: (config($env) ?: null);
    }

    /** Where a provider's key comes from: 'console', 'env' or null (not configured). */
    public static function aiKeySource(?string $provider = null): ?string
    {
        $provider ??= self::aiProvider();
        [$setting, , $env] = self::AI_PROVIDERS[$provider];
        if (filled(self::all()[$setting] ?? null) && self::aiApiKey($provider) !== config($env)) {
            return 'console';
        }

        return filled(config($env)) ? 'env' : null;
    }

    /** Save (encrypted) or remove a provider's console key. */
    public static function setAiApiKey(?string $key, string $provider = 'anthropic'): void
    {
        $setting = self::query()->firstOrNew(['key' => self::AI_PROVIDERS[$provider][0]], ['group' => 'platform']);
        $setting->value = filled($key) ? \Illuminate\Support\Facades\Crypt::encryptString(trim($key)) : null;
        $setting->save();
        self::$cache = null;
    }

    /** A stored secret, decrypted; null when unset or unreadable (APP_KEY changed). */
    public static function secret(string $key): ?string
    {
        $stored = self::all()[$key] ?? null;
        if (blank($stored)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $stored);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    public static function setSecret(string $key, ?string $value): void
    {
        $setting = self::query()->firstOrNew(['key' => $key], ['group' => 'platform']);
        $setting->value = filled($value) ? \Illuminate\Support\Facades\Crypt::encryptString(trim($value)) : null;
        $setting->save();
        self::$cache = null;
    }

    /** A site text in the visitor's language, or null to keep the built-in wording. */
    public static function text(string $key): ?string
    {
        $value = self::get($key.'_'.(app()->getLocale() === 'en' ? 'en' : 'ar'));

        return filled($value) ? (string) $value : null;
    }

    /** @return array<string, string> network => profile URL, only those filled in */
    public static function socialLinks(): array
    {
        return collect(array_keys(self::SOCIAL))->mapWithKeys(fn ($network) => [$network => (string) self::get('social_'.$network)])
            ->filter(fn ($url) => filled($url) && preg_match('#^https?://#i', $url))->all();
    }

    /** @return array{src: string, tenant: string, id: string}|null the chat widget (always starts closed; the visitor opens it) the chat widget, or null when off */
    public static function widget(): ?array
    {
        $id = (string) (self::get('widget_id') ?: config('rowad.site_widget.widget'));
        $enabled = self::get('widget_enabled');
        if (blank($id) || ($enabled !== null && ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN))) {
            return null;
        }

        return [
            'src' => (string) (self::get('widget_src') ?: config('rowad.site_widget.src')),
            'tenant' => (string) (self::get('widget_tenant') ?: config('rowad.site_widget.tenant')),
            'id' => $id,
        ];
    }

    /** @return list<string> who hears about new offices: the configured list, else the contact email */
    public static function signupAlertEmails(): array
    {
        $list = array_values(array_filter(array_map('trim', (array) self::get('signup_alert_emails')), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));

        return $list !== [] ? $list : array_values(array_filter([self::contact('email')]));
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
