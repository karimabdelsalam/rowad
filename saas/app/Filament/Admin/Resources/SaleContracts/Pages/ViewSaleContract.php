<?php

namespace App\Filament\Admin\Resources\SaleContracts\Pages;

use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSaleContract extends ViewRecord
{
    protected static string $resource = SaleContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
