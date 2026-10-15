<?php

namespace App\Filament\Admin\Resources\MessageTemplates\Pages;

use App\Filament\Admin\Resources\MessageTemplates\MessageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
