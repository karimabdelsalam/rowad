<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A salary advance (سلفة), recovered in monthly instalments through payroll. */
#[Guarded(['id'])]
class EmployeeAdvance extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'remaining' => 'decimal:2',
            'issued_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
