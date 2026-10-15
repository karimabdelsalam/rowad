<?php

namespace App\Models\Lookups;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تصنيف عقار (legacy building_cats) */
#[Guarded(['id'])]
class PropertyCategory extends Model
{
    protected $table = 'property_categories';

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}
