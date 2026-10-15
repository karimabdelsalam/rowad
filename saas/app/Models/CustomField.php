<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class CustomField extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomFieldGroup::class, 'custom_field_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
