<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\PropertyCategories\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\PropertyCategories\PropertyCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePropertyCategories extends ManageRecords
{
    protected static string $resource = PropertyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
