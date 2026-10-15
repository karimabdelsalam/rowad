<?php

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOwners extends ListRecords
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
