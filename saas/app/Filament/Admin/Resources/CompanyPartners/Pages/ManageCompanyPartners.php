<?php

namespace App\Filament\Admin\Resources\CompanyPartners\Pages;

use App\Filament\Admin\Resources\CompanyPartners\CompanyPartnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanyPartners extends ManageRecords
{
    protected static string $resource = CompanyPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
