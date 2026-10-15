<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Models\Lookups\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** حركة في دفتر حساب المالك (legacy transactions) */
#[Guarded(['id'])]
class OwnerTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => LedgerDirection::class,
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'due_date' => 'date',
            'is_settled' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OwnerTransaction::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class, 'parent_id');
    }
}
