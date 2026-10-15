<?php

namespace App\Models;

use App\Models\Lookups\PropertyCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class CustomFieldGroup extends Model
{
    public function propertyCategory(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }
}
