<?php

namespace App\Filament\Admin\Resources\PrintTemplates\Pages;

use App\Filament\Admin\Resources\PrintTemplates\PrintTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintTemplate extends ViewRecord
{
    protected static string $resource = PrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
