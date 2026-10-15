<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Pages;

use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReceiptVoucher extends EditRecord
{
    protected static string $resource = ReceiptVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
