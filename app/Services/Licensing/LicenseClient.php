<?php

namespace App\Services\Licensing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The installation side (a customer's own server, LICENSE_MODE=self_hosted): activates the license key
 * against aqarnova.com, checks it daily, and decides from the signed token whether the system may run.
 * If the server cannot be reached the last token keeps working for token_days + grace_days.
 */
class LicenseClient
{
    public const VALID = 'valid';

    public const GRACE = 'grace';

    public const INVALID = 'invalid';

    public static function selfHosted(): bool
    {
        return config('rowad.license.mode') === 'self_hosted';
    }

    /** Files live outside the per-office storage folders (tenancy changes storage_path() inside an office). */
    private function path(string $file): string
    {
        return base_path('storage/app/'.$file);
    }

    public function instanceId(): string
    {
        $file = $this->path('license-instance');
        if (! is_file($file)) {
            @mkdir(dirname($file), 0775, true);
            file_put_contents($file, (string) Str::uuid());
        }

        return trim((string) file_get_contents($file));
    }

    public function domain(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    /** @return array{ok: bool, code: ?string, message: ?string} */
    public function activate(?string $key = null): array
    {
        return $this->call('activate', [
            'key' => $key ?? config('rowad.license.key'),
            'domain' => $this->domain(),
            'instance_id' => $this->instanceId(),
            'version' => (string) config('app.version', '1.0'),
            'php' => PHP_VERSION,
        ], $key);
    }

    /** Daily check (rowad:license:check). A network failure keeps the last token. */
    public function check(): array
    {
        return $this->call('verify', [
            'key' => $this->stored()['key'] ?? config('rowad.license.key'),
            'instance_id' => $this->instanceId(),
            'version' => (string) config('app.version', '1.0'),
        ]);
    }

    public function deactivate(): void
    {
        $this->call('deactivate', ['key' => $this->stored()['key'] ?? config('rowad.license.key'), 'instance_id' => $this->instanceId()]);
        @unlink($this->path('license.json'));
    }

    private function call(string $action, array $body, ?string $key = null): array
    {
        try {
            $response = Http::acceptJson()->timeout(20)->post(config('rowad.license.server').'/api/license/'.$action, $body);
        } catch (Throwable $e) {
            $this->remember(['last_error' => 'unreachable', 'last_attempt_at' => now()->toIso8601String()]);

            return ['ok' => false, 'code' => 'unreachable', 'message' => 'Could not reach the license server.'];
        }

        $json = $response->json() ?? [];
        if (filled($json['token'] ?? null)) {
            $this->remember([
                'token' => $json['token'],
                'key' => $key ?? $body['key'],
                'last_ok_at' => now()->toIso8601String(),
                'last_error' => $json['code'] ?? null,
                'last_attempt_at' => now()->toIso8601String(),
            ]);
        } else {
            $this->remember(['last_error' => $json['code'] ?? 'http_'.$response->status(), 'last_attempt_at' => now()->toIso8601String()]);
        }

        return ['ok' => (bool) ($json['ok'] ?? false), 'code' => $json['code'] ?? null, 'message' => $json['message'] ?? null];
    }

    /** @return array<string, mixed> */
    public function stored(): array
    {
        $file = $this->path('license.json');

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    private function remember(array $values): void
    {
        self::forget();
        @mkdir(dirname($this->path('license.json')), 0775, true);
        file_put_contents($this->path('license.json'), json_encode([...$this->stored(), ...$values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Whether this installation may run, from the stored signed token only.
     *
     * @return array{state: string, reason: ?string, payload: ?array, days_offline: ?int}
     */
    public function state(): array
    {
        // Several checks ask on every page; the answer only changes when the token file does.
        $file = $this->path('license.json');
        $stamp = is_file($file) ? filemtime($file).':'.filesize($file) : 'none';
        if (self::$memo !== null && self::$memo[0] === $stamp && self::$memo[2] === now()->format('YmdH')) {
            return self::$memo[1];
        }
        $state = $this->computeState();
        self::$memo = [$stamp, $state, now()->format('YmdH')];

        return $state;
    }

    /** @var array{0: string, 1: array, 2: string}|null */
    private static ?array $memo = null;

    public static function forget(): void
    {
        self::$memo = null;
        clearstatcache();
    }

    private function computeState(): array
    {
        if (! self::selfHosted()) {
            return ['state' => self::VALID, 'reason' => null, 'payload' => null, 'days_offline' => null];
        }
        $stored = $this->stored();
        $publicKey = (string) config('rowad.license.public_key');
        $invalid = fn (string $reason, ?array $payload = null) => ['state' => self::INVALID, 'reason' => $reason, 'payload' => $payload, 'days_offline' => null];

        if (blank($stored['token'] ?? null)) {
            return $invalid('not_activated');
        }
        if (blank($publicKey)) {
            return $invalid('no_public_key');
        }
        $payload = LicenseToken::verify($stored['token'], $publicKey);
        if ($payload === null) {
            return $invalid('tampered');
        }
        if (($payload['instance'] ?? null) !== $this->instanceId()) {
            return $invalid('other_server', $payload);
        }
        if (($payload['status'] ?? null) !== 'active') {
            return $invalid((string) $payload['status'], $payload);
        }
        if (filled($payload['expires_at'] ?? null) && Carbon::parse($payload['expires_at'])->isPast()) {
            return $invalid('expired', $payload);
        }

        $validUntil = Carbon::parse($payload['valid_until']);
        if ($validUntil->isFuture()) {
            return ['state' => self::VALID, 'reason' => null, 'payload' => $payload, 'days_offline' => null];
        }
        $offline = (int) Carbon::parse($payload['issued_at'])->diffInDays(now());
        if ($validUntil->copy()->addDays((int) config('rowad.license.grace_days'))->isFuture()) {
            return ['state' => self::GRACE, 'reason' => 'offline', 'payload' => $payload, 'days_offline' => $offline];
        }

        return ['state' => self::INVALID, 'reason' => 'offline_too_long', 'payload' => $payload, 'days_offline' => $offline];
    }

    /** null = no limit from the license (or not self-hosted) */
    public function limit(string $name): ?int
    {
        $value = $this->state()['payload']['limits'][$name] ?? null;

        return $value === null ? null : (int) $value;
    }

    /** null = the license does not restrict features */
    public function features(): ?array
    {
        $features = $this->state()['payload']['features'] ?? null;

        return is_array($features) && $features !== [] ? $features : null;
    }

    public static function reasonText(?string $reason): string
    {
        return match ($reason) {
            'not_activated' => __('لم يُفعَّل الترخيص على هذا السيرفر بعد.'),
            'no_public_key' => __('مفتاح التحقق LICENSE_PUBLIC_KEY غير مضبوط.'),
            'tampered' => __('ملف الترخيص غير صالح أو تم تعديله.'),
            'other_server' => __('هذا الترخيص مفعّل على سيرفر آخر.'),
            'suspended' => __('الترخيص موقوف. تواصل مع عقار نوفا.'),
            'revoked' => __('الترخيص ملغى.'),
            'expired' => __('انتهت مدة الترخيص. جدّده مع عقار نوفا.'),
            'domain_not_allowed' => __('الترخيص لا يسمح بهذا النطاق.'),
            'offline_too_long' => __('تعذّر التحقق من الترخيص منذ فترة طويلة. تأكد من اتصال السيرفر بالإنترنت.'),
            'offline' => __('يعمل النظام دون التحقق من الترخيص مؤقتًا.'),
            default => (string) $reason,
        };
    }
}
