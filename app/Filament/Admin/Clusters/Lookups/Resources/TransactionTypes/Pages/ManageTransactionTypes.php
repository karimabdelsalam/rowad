<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\TransactionTypes\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\TransactionTypes\TransactionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTransactionTypes extends ManageRecords
{
    protected static string $resource = TransactionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
