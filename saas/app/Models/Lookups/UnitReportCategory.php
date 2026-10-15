<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** تصنيف تقارير الوحدات */
#[Guarded(['id'])]
class UnitReportCategory extends Model
{
    protected $table = 'unit_report_categories';
}
