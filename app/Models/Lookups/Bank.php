<?php

namespace App\Models\Lookups;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** بنك */
#[Guarded(['id'])]
class Bank extends Model
{
    protected $table = 'banks';

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
