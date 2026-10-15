<?php

namespace App\Filament\Admin\Resources\PrintTemplates\Pages;

use App\Filament\Admin\Resources\PrintTemplates\PrintTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPrintTemplate extends EditRecord
{
    protected static string $resource = PrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
