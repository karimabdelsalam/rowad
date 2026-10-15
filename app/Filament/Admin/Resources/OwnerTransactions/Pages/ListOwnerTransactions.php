<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Pages;

use App\Filament\Admin\Resources\OwnerTransactions\OwnerTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListOwnerTransactions extends ListRecords
{
    protected static string $resource = OwnerTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
