<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One month's salaries for the whole office. */
#[Guarded(['id'])]
class PayrollRun extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_gosi_employer' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periodLabel(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->locale(app()->getLocale())->translatedFormat('F Y');
    }

    public function isEditable(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /** Recompute the totals from the lines (lines are edited by hand before approval). */
    public function refreshTotals(): void
    {
        $items = $this->items()->get();
        $this->forceFill([
            'total_gross' => $items->sum('gross'),
            'total_deductions' => $items->sum(fn (PayrollItem $i) => $i->deductionsTotal()),
            'total_net' => $items->sum('net'),
            'total_gosi_employer' => $items->sum('gosi_employer'),
        ])->save();
    }
}
