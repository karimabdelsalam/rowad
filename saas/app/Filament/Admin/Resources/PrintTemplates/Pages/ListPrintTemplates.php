<?php

namespace App\Filament\Admin\Resources\PrintTemplates\Pages;

use App\Filament\Admin\Resources\PrintTemplates\PrintTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrintTemplates extends ListRecords
{
    protected static string $resource = PrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
