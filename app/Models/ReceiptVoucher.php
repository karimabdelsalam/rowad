<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\PaymentMethod;
use App\Models\Lookups\TransactionType;
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

/** سند قبض (legacy statement_in) */
#[Guarded(['id'])]
class ReceiptVoucher extends Model implements HasMedia
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

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function installmentPayments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class);
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
