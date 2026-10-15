<?php

namespace App\Models;

use App\Enums\Calendar;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جزاء موظف (legacy punish) */
#[Guarded(['id'])]
class EmployeePenalty extends Model
{
    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'calendar' => Calendar::class,
            'issued_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
