<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRentContract extends ViewRecord
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
