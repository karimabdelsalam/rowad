<?php

namespace App\Models;

use App\Models\Lookups\PropertyCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** المبنى / المجمع (legacy building_locs) */
#[Guarded(['id'])]
class Building extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, \App\Models\Concerns\BelongsToBranch;

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'property_category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
