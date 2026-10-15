<?php

namespace App\Models\Lookups;

use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** جهة حكومية */
#[Guarded(['id'])]
class GovernmentOrganization extends Model
{
    protected $table = 'government_organizations';

    public function receiptVouchers(): MorphMany
    {
        return $this->morphMany(ReceiptVoucher::class, 'party');
    }

    public function paymentVouchers(): MorphMany
    {
        return $this->morphMany(PaymentVoucher::class, 'party');
    }
}
