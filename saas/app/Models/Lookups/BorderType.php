<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** حدود/أطوال (legacy building_borders) */
#[Guarded(['id'])]
class BorderType extends Model
{
    protected $table = 'border_types';

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
        ];
    }
}
