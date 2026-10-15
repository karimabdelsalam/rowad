<?php

namespace App\Filament\Admin\Resources\SaleContracts\Pages;

use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Contracts\ContractService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSaleContract extends EditRecord
{
    use HandlesContractParties;

    protected static string $resource = SaleContractResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['parties'] = $this->partiesForForm($this->getRecord());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$attributes, $parties, $terms] = $this->splitContractData($data);
        if (isset($attributes['payment_plan']['parts'])) {
            $attributes['payment_plan']['parts'] = array_values($attributes['payment_plan']['parts']);
        }

        return $this->guardBusinessRules(fn () => app(ContractService::class)->updateSale($record, $attributes, $parties, $terms));
    }
}
