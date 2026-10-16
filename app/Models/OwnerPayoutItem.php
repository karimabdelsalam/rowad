<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One owner's line in a payout run, with the bank details as they were when it was prepared. */
#[Guarded(['id'])]
class OwnerPayoutItem extends Model
{
    protected function casts(): array
    {
        return [
            'dues' => 'decimal:2',
            'deductions' => 'decimal:2',
            'amount' => 'decimal:2',
            'entry_ids' => 'array',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(OwnerPayout::class, 'owner_payout_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withTrashed();
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }
}
