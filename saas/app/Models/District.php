<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class District extends Model
{
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
