<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\Cities\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\Cities\CityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCities extends ManageRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
