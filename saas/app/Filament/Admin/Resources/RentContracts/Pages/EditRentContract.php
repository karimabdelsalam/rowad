<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Contracts\ContractService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRentContract extends EditRecord
{
    use HandlesContractParties;

    protected static string $resource = RentContractResource::class;

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

        return $this->guardBusinessRules(fn () => app(ContractService::class)->updateRent($record, $attributes, $parties, $terms));
    }
}
