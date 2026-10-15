<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentContracts extends ListRecords
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
