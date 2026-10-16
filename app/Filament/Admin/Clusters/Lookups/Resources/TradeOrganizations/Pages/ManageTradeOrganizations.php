<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\TradeOrganizations\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\TradeOrganizations\TradeOrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTradeOrganizations extends ManageRecords
{
    protected static string $resource = TradeOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\TradeOrganization::class, 'title'),
        ];
    }
}
