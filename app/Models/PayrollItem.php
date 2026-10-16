<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee's line in a payroll run. gross = pay + allowances + overtime + bonus; net = gross - deductions. */
#[Guarded(['id'])]
class PayrollItem extends Model
{
    public const MONEY = ['basic', 'housing', 'transport', 'other_allowances', 'overtime', 'bonus', 'penalties', 'advances', 'unpaid_leave',
        'gosi_employee', 'gosi_employer', 'other_deductions', 'gross', 'net'];

    protected function casts(): array
    {
        return array_fill_keys(self::MONEY, 'decimal:2');
    }

    protected static function booted(): void
    {
        static::saving(fn (self $item) => $item->recalculate());
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function deductionsTotal(): float
    {
        return round((float) $this->penalties + (float) $this->advances + (float) $this->unpaid_leave + (float) $this->gosi_employee + (float) $this->other_deductions, 2);
    }

    public function recalculate(): void
    {
        $this->gross = round((float) $this->basic + (float) $this->housing + (float) $this->transport + (float) $this->other_allowances + (float) $this->overtime + (float) $this->bonus, 2);
        $this->net = round($this->gross - $this->deductionsTotal(), 2);
    }
}
