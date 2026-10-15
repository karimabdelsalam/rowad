<?php

namespace App\Filament\Admin\Resources\ContractTerms\Pages;

use App\Filament\Admin\Resources\ContractTerms\ContractTermResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractTerms extends ManageRecords
{
    protected static string $resource = ContractTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
