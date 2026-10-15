<?php

namespace App\Filament\Admin\Resources\CustomFieldGroups\Pages;

use App\Filament\Admin\Resources\CustomFieldGroups\CustomFieldGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomFieldGroups extends ManageRecords
{
    protected static string $resource = CustomFieldGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
