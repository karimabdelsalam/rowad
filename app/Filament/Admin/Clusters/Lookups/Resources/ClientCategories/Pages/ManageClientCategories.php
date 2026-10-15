<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\ClientCategories\Pages;

use App\Filament\Admin\Clusters\Lookups\Resources\ClientCategories\ClientCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClientCategories extends ManageRecords
{
    protected static string $resource = ClientCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
