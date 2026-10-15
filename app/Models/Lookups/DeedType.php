<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** نوع صك */
#[Guarded(['id'])]
class DeedType extends Model
{
    protected $table = 'deed_types';
}
