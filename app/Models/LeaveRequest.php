<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class LeaveRequest extends Model
{
    protected $attributes = ['calendar' => 'gregorian', 'status' => 'pending'];

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'calendar' => Calendar::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $leave) {
            if ($leave->start_date && $leave->end_date) {
                $leave->days = (int) $leave->start_date->diffInDays($leave->end_date) + 1;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Days of this leave that fall inside the given month (for unpaid-leave deductions). */
    public function daysInMonth(int $year, int $month): int
    {
        $from = \Carbon\CarbonImmutable::create($year, $month, 1);
        $to = $from->endOfMonth();
        $start = $this->start_date->toImmutable()->max($from);
        $end = $this->end_date->toImmutable()->min($to);

        return $start->gt($end) ? 0 : (int) $start->diffInDays($end) + 1;
    }
}
