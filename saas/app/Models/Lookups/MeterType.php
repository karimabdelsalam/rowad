<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** نوع عداد */
#[Guarded(['id'])]
class MeterType extends Model
{
    protected $table = 'meter_types';
}
