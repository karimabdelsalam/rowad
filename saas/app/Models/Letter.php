<?php

namespace App\Models;

use App\Enums\Calendar;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class Letter extends Model
{
    protected function casts(): array
    {
        return [
            'calendar' => Calendar::class,
            'letter_date' => 'date',
            'is_official' => 'boolean',
            'rent_raise_amount' => 'decimal:2',
            'data' => 'array',
            'marks_unit_reserved' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
