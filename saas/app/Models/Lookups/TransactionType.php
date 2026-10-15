<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** نوع حركة في دفتر المالك */
#[Guarded(['id'])]
class TransactionType extends Model
{
    protected $table = 'transaction_types';

    protected function casts(): array
    {
        return [
            'is_income' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
