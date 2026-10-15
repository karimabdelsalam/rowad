<?php

namespace App\Filament\Admin\Resources\PaymentVouchers\Pages;

use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Finance\VoucherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePaymentVoucher extends CreateRecord
{
    use HandlesContractParties;

    protected static string $resource = PaymentVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $entries = $data['owner_transaction_ids'] ?? [];
        $chargeTo = $data['charge_to'] ?? null;
        $withVat = (bool) ($data['with_vat'] ?? false);
        unset($data['owner_transaction_ids'], $data['charge_to'], $data['with_vat']);
        if (blank($data['party_type'] ?? null)) {
            $data['party_type'] = $data['party_id'] = null;
        }

        return $this->guardBusinessRules(fn () => app(VoucherService::class)->createPayment($data, $chargeTo, array_map('intval', $entries), $withVat));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
