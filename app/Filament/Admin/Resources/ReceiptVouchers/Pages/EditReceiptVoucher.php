<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Pages;

use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Finance\VoucherService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditReceiptVoucher extends EditRecord
{
    use HandlesContractParties;

    protected static string $resource = ReceiptVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->using(fn (Model $record) => $this->guardBusinessRules(fn () => app(VoucherService::class)->delete($record) ?? true)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record->update($data);
            app(VoucherService::class)->syncBank($record);

            return $record;
        });
    }
}
