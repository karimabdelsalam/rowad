<?php

namespace App\Models\Lookups;

use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** جهة تجارية */
#[Guarded(['id'])]
class TradeOrganization extends Model
{
    protected $table = 'trade_organizations';

    public function receiptVouchers(): MorphMany
    {
        return $this->morphMany(ReceiptVoucher::class, 'party');
    }

    public function paymentVouchers(): MorphMany
    {
        return $this->morphMany(PaymentVoucher::class, 'party');
    }
}
