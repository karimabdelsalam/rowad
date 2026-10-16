<?php

namespace App\Models;

use App\Enums\ViewingStatus;
use App\Models\Concerns\ScopedByUnitBranch;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * موعد معاينة. `starts_at` holds the office's local wall-clock time (as the visitor chose it), so screens,
 * messages and the slot grid show the same time without timezone conversion.
 */
#[Guarded(['id'])]
class Viewing extends Model
{
    use ScopedByUnitBranch;

    protected $attributes = ['status' => 'booked', 'duration_minutes' => 30, 'source' => 'website'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'status' => ViewingStatus::class,
            'reminded_at' => 'datetime',
        ];
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', ViewingStatus::Booked)->where('starts_at', '>=', \App\Services\Viewings\ViewingSchedule::now()->startOfDay());
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
