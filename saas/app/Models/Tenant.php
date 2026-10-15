<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/** A subscribing real-estate office. Lives in the central database. */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'email', 'phone', 'plan_id', 'status', 'trial_ends_at'];
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        // The status filter must go inside ofMany(): a plain where() is applied after "latest" is picked among all rows.
        return $this->hasOne(Subscription::class)->ofMany(['starts_at' => 'max', 'id' => 'max'], fn ($query) => $query->where('status', 'active'));
    }

    public function isAccessible(): bool
    {
        return match ($this->status) {
            self::STATUS_ACTIVE, self::STATUS_PAST_DUE => true,
            self::STATUS_TRIAL => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            default => false,
        };
    }
}
