<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\UnitReportCategories\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\UnitReportCategories\UnitReportCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnitReportCategories extends ManageRecords
{
    protected static string $resource = UnitReportCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            \App\Filament\Admin\Clusters\Lookups\BulkAddAction::make(\App\Models\Lookups\UnitReportCategory::class, 'title'),
        ];
    }
}
