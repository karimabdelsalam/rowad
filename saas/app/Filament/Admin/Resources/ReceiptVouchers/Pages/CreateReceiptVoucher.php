<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Pages;

use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Finance\VoucherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceiptVoucher extends CreateRecord
{
    use HandlesContractParties;
    use \App\Filament\Concerns\PrefillsOwnerEntries;

    protected static string $resource = ReceiptVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $installments = $data['installment_ids'] ?? [];
        $entries = $data['owner_transaction_ids'] ?? [];
        unset($data['installment_ids'], $data['owner_transaction_ids']);
        if (blank($data['party_type'] ?? null)) {
            $data['party_type'] = $data['party_id'] = null;
        }

        return $this->guardBusinessRules(fn () => app(VoucherService::class)->createReceipt($data, array_map('intval', $installments), array_map('intval', $entries)));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
