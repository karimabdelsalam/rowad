<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/** بند عقد قابل لإعادة الاستخدام (legacy contract_rights) */
#[Guarded(['id'])]
class ContractTerm extends Model
{
}
