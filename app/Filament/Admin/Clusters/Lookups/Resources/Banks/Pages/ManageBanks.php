<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\Banks\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\Banks\BankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBanks extends ManageRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
