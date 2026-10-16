<?php

namespace App\Models;

use App\Enums\OwnerPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One payout run: the net dues of several owners, paid by bank transfer in one go. */
#[Guarded(['id'])]
class OwnerPayout extends Model
{
    protected $attributes = ['status' => 'prepared'];

    protected function casts(): array
    {
        return [
            'status' => OwnerPayoutStatus::class,
            'issued_at' => 'date',
            'dues_until' => 'date',
            'total' => 'decimal:2',
            'transferred_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OwnerPayoutItem::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
