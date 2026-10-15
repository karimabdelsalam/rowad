<?php

namespace App\Filament\Admin\Resources\PaymentVouchers\Pages;

use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentVoucher extends CreateRecord
{
    protected static string $resource = PaymentVoucherResource::class;
}
