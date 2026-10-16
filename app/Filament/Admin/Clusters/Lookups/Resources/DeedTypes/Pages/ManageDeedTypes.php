<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\DeedTypes\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\DeedTypes\DeedTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeedTypes extends ManageRecords
{
    protected static string $resource = DeedTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\DeedType::class, 'title'),
        ];
    }
}
