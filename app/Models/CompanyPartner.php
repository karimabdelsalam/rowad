<?php

namespace App\Models;

use App\Enums\IdType;
use App\Models\Concerns\HasPersonName;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** شريك في المنشأة (legacy company_owners) */
#[Guarded(['id'])]
class CompanyPartner extends Model
{
    use HasPersonName;

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'share_percent' => 'decimal:2',
        ];
    }
}
