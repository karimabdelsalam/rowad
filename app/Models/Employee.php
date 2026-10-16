<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\IdType;
use App\Models\Concerns\HasPersonName;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Guarded(['id'])]
class Employee extends Model implements HasMedia
{
    use HasPersonName, SoftDeletes, InteractsWithMedia;

    protected $attributes = ['calendar' => 'gregorian', 'gosi_subscribed' => true, 'status' => 'active', 'employment_type' => 'full_time', 'weekly_hours' => 48];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('id_expiry_date')) {
                $model->id_expiry_notified_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'id_expiry_notified_at' => 'datetime',
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'id_expiry_date' => 'date',
            'start_date' => 'date',
            'contract_end_date' => 'date',
            'termination_date' => 'date',
            'salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowances' => 'decimal:2',
            'gosi_subscribed' => 'boolean',
            'rent_commission' => 'decimal:2',
            'sale_commission' => 'decimal:2',
            'calendar' => Calendar::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(EmployeePenalty::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Lookups\Bank::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'terminated');
    }

    /** Basic salary plus every allowance: the "wage" of the Labour Law (end of service, unpaid-leave deductions). */
    public function totalWage(): float
    {
        return round((float) $this->salary + (float) $this->housing_allowance + (float) $this->transport_allowance + (float) $this->other_allowances, 2);
    }

    /** GOSI and leave rules differ for Saudis: decided by the nationality text (or a national ID). */
    public function isSaudi(): bool
    {
        $n = mb_strtolower(trim((string) $this->nationality));

        return str_contains($n, 'سعود') || str_contains($n, 'saudi') || ($n === '' && $this->id_type === IdType::NationalId);
    }

    /** Completed years of service at the given date (fractional). */
    public function serviceYears(?\DateTimeInterface $at = null): float
    {
        if ($this->start_date === null) {
            return 0.0;
        }
        $end = \Carbon\CarbonImmutable::instance($at ?? now());

        return round(max(0, $this->start_date->toImmutable()->diffInDays($end)) / 365, 2);
    }

    public function receiptVouchers(): MorphMany
    {
        return $this->morphMany(ReceiptVoucher::class, 'party');
    }

    public function paymentVouchers(): MorphMany
    {
        return $this->morphMany(PaymentVoucher::class, 'party');
    }
}
