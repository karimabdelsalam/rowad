<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\PaymentMethod;
use App\Models\Lookups\ExpenseType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** سند صرف (legacy statement_out) */
#[Guarded(['id'])]
class PaymentVoucher extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, LogsActivity, \App\Models\Concerns\BelongsToBranch;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'calendar' => Calendar::class,
            'issued_at' => 'date',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'cheque_date' => 'date',
        ];
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankTransaction(): MorphOne
    {
        return $this->morphOne(BankTransaction::class, 'voucher');
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function ownerTransactions(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
