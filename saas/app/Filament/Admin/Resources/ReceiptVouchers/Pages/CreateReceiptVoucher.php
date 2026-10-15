<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Pages;

use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiptVoucher extends CreateRecord
{
    protected static string $resource = ReceiptVoucherResource::class;
}
