<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** نوع مصروف (legacy outgoings_types) */
#[Guarded(['id'])]
class ExpenseType extends Model
{
    protected $table = 'expense_types';
}
