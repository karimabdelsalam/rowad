<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\MeterTypes\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\MeterTypes\MeterTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMeterTypes extends ManageRecords
{
    protected static string $resource = MeterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\MeterType::class, 'title'),
        ];
    }
}
