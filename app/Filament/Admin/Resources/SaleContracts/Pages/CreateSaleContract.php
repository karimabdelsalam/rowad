<?php

namespace App\Filament\Admin\Resources\SaleContracts\Pages;

use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Contracts\ContractService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSaleContract extends CreateRecord
{
    use HandlesContractParties;

    protected static string $resource = SaleContractResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$attributes, $parties, $terms] = $this->splitContractData($data);
        $attributes['payment_plan'] ??= [];
        if (isset($attributes['payment_plan']['parts'])) {
            $attributes['payment_plan']['parts'] = array_values($attributes['payment_plan']['parts']);
        }

        return $this->guardBusinessRules(fn () => app(ContractService::class)->createSale($attributes, $parties, $terms));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
