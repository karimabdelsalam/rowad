<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** A license for a self-hosted copy (central database). */
#[Guarded(['id'])]
class License extends Model
{
    use CentralConnection;

    public const STATUSES = ['active' => 'فعّال', 'suspended' => 'موقوف', 'revoked' => 'ملغى'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'allowed_domains' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'support_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (License $license) {
            $license->key ??= self::generateKey();
            $license->starts_at ??= now();
        });
    }

    /** AQN-XXXXX-XXXXX-XXXXX-XXXXX, without characters that are easy to misread (0/O, 1/I/L). */
    public static function generateKey(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $groups = [];
            for ($g = 0; $g < 4; $g++) {
                $groups[] = implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 5)));
            }
            $key = 'AQN-'.implode('-', $groups);
        } while (self::where('key', $key)->exists());

        return $key;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function activeActivations(): HasMany
    {
        return $this->activations()->where('status', 'active');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** null when usable, otherwise the reason shown to the installation. */
    public function problem(): ?string
    {
        return match (true) {
            $this->status === 'revoked' => 'revoked',
            $this->status === 'suspended' => 'suspended',
            $this->isExpired() => 'expired',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'not_started',
            default => null,
        };
    }

    /** @return list<string> */
    public function effectiveFeatures(): array
    {
        return array_values($this->features ?? $this->plan?->features ?? []);
    }

    public function allowsDomain(string $domain): bool
    {
        $domain = Str::lower($domain);
        if (empty($this->allowed_domains)) {
            return true;
        }
        foreach ($this->allowed_domains as $allowed) {
            $allowed = Str::lower(trim($allowed));
            if ($domain === $allowed || (str_starts_with($allowed, '*.') && str_ends_with($domain, substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }

    public function maskedKey(): string
    {
        return substr($this->key, 0, 10).'…'.substr($this->key, -5);
    }
}
