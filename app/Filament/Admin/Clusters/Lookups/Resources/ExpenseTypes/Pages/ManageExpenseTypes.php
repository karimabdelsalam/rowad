<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\ExpenseTypes\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\ExpenseTypes\ExpenseTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenseTypes extends ManageRecords
{
    protected static string $resource = ExpenseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\ExpenseType::class, 'title'),
        ];
    }
}
