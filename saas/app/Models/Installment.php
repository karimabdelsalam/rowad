<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\InstallmentType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** قسط / مستحق على المستأجر أو المشتري (legacy payments) */
#[Guarded(['id'])]
class Installment extends Model
{
    use LogsActivity;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'last_reminded_at' => 'datetime',
            'type' => InstallmentType::class,
            'calendar' => Calendar::class,
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'seller_commission' => 'decimal:2',
            'buyer_commission' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'is_collected' => 'boolean',
            'collected_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function contract(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiptVoucher(): BelongsTo
    {
        return $this->belongsTo(ReceiptVoucher::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class);
    }

    public function ownerTransactions(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class);
    }

    /** SQL for what was actually paid: a full collection does not touch paid_amount (that holds partial payments only). */
    public const PAID_SQL = 'CASE WHEN is_collected = 1 THEN amount ELSE paid_amount END';

    public function paidTotal(): float
    {
        return $this->is_collected ? round((float) $this->amount, 2) : round((float) $this->paid_amount, 2);
    }

    public function remainingAmount(): float
    {
        return $this->is_collected ? 0.0 : round((float) $this->amount - (float) $this->paid_amount, 2);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_collected', false);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->whereDate('due_date', '<', now());
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
