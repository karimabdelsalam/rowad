<?php

namespace App\Models;

use App\Enums\EndOfServiceReason;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class EndOfServiceSettlement extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'reason' => EndOfServiceReason::class,
            'termination_date' => 'date',
            'service_years' => 'decimal:2',
            'last_wage' => 'decimal:2',
            'award' => 'decimal:2',
            'leave_balance_days' => 'decimal:2',
            'leave_amount' => 'decimal:2',
            'other_dues' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'calculation' => 'array',
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
