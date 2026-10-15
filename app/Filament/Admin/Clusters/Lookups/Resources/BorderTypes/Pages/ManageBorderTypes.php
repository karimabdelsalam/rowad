<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\BorderTypes\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\BorderTypes\BorderTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBorderTypes extends ManageRecords
{
    protected static string $resource = BorderTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
