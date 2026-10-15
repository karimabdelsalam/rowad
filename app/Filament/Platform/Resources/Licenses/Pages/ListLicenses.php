<?php

namespace App\Filament\Platform\Resources\Licenses\Pages;

use App\Filament\Platform\Resources\Licenses\LicenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('ترخيص جديد'))];
    }
}
