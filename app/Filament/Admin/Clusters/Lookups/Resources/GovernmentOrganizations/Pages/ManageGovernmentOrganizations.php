<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\GovernmentOrganizations\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\GovernmentOrganizations\GovernmentOrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGovernmentOrganizations extends ManageRecords
{
    protected static string $resource = GovernmentOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\GovernmentOrganization::class, 'title'),
        ];
    }
}
