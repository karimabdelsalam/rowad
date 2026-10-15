<?php

namespace App\Models;

use App\Models\Lookups\DeedType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One title deed of a unit (legacy building.deed rows). */
#[Guarded(['id'])]
class UnitDeed extends Model
{
    /** The first deed is mirrored on the unit (deed_type_id / deed_number) for list search. */
    protected static function booted(): void
    {
        $sync = function (UnitDeed $deed) {
            $first = static::where('unit_id', $deed->unit_id)->orderBy('id')->first();
            Unit::withoutEvents(fn () => Unit::whereKey($deed->unit_id)->update([
                'deed_type_id' => $first?->deed_type_id, 'deed_number' => $first?->number,
            ]));
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function deedType(): BelongsTo
    {
        return $this->belongsTo(DeedType::class);
    }
}
