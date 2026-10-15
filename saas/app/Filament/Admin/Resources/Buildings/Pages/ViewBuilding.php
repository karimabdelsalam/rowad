<?php

namespace App\Filament\Admin\Resources\Buildings\Pages;

use App\Filament\Admin\Resources\Buildings\BuildingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBuilding extends ViewRecord
{
    protected static string $resource = BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
