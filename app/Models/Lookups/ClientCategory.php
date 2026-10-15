<?php

namespace App\Models\Lookups;

use App\Models\Client;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تصنيف عميل (legacy buyer_cats) */
#[Guarded(['id'])]
class ClientCategory extends Model
{
    protected $table = 'client_categories';

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
