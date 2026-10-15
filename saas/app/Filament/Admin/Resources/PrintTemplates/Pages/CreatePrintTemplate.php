<?php

namespace App\Filament\Admin\Resources\PrintTemplates\Pages;

use App\Filament\Admin\Resources\PrintTemplates\PrintTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrintTemplate extends CreateRecord
{
    protected static string $resource = PrintTemplateResource::class;
}
