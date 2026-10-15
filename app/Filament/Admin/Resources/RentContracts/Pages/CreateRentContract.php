<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Contracts\ContractService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRentContract extends CreateRecord
{
    use HandlesContractParties;

    protected static string $resource = RentContractResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$attributes, $parties, $terms] = $this->splitContractData($data);

        return $this->guardBusinessRules(fn () => app(ContractService::class)->createRent($attributes, $parties, $terms));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
