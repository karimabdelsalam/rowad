<?php

namespace App\Models;

use App\Models\Lookups\MeterType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class UnitMeter extends Model
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function meterType(): BelongsTo
    {
        return $this->belongsTo(MeterType::class);
    }
}
