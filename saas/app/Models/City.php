<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class City extends Model
{
    protected $table = 'cities';

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
