<?php

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOwner extends ViewRecord
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
