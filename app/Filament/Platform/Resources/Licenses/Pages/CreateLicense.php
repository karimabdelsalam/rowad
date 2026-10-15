<?php

namespace App\Filament\Platform\Resources\Licenses\Pages;

use App\Filament\Platform\Resources\Licenses\LicenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLicense extends CreateRecord
{
    protected static string $resource = LicenseResource::class;

    /** The key is generated on save; the view page shows it with the setup instructions. */
    protected function getRedirectUrl(): string
    {
        return LicenseResource::getUrl('view', ['record' => $this->record]);
    }
}
