<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    public const UPDATED_AT = null;   // الجدول يحمل created_at فقط

    protected $guarded = ['id'];

    protected $casts = [
        'start_date'     => 'date',
        'expires_at'     => 'date',
        'last_ping_at'   => 'datetime',
        'patients_count' => 'integer',
        'users_count'    => 'integer',
    ];

    public const STATUSES = [
        'trial'     => 'تجريبي',
        'active'    => 'مشترك',
        'suspended' => 'موقوف',
        'cancelled' => 'ملغي',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** أيام متبقية حتى انتهاء الاشتراك (سالبة إن انتهى، null إن لا تاريخ) */
    public function daysLeft(): ?int
    {
        return $this->expires_at?->startOfDay()->diffInDays(now()->startOfDay(), false) * -1;
    }

    public function isSaas(): bool
    {
        return $this->db_name !== '';
    }

    public function scopeLive($q)
    {
        return $q->whereIn('status', ['trial', 'active']);
    }
}
