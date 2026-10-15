<?php

namespace App\Filament\Admin\Resources\GovernmentLicenses\Pages;

use App\Filament\Admin\Resources\GovernmentLicenses\GovernmentLicenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGovernmentLicenses extends ManageRecords
{
    protected static string $resource = GovernmentLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
