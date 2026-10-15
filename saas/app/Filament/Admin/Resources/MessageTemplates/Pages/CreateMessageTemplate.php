<?php

namespace App\Filament\Admin\Resources\MessageTemplates\Pages;

use App\Filament\Admin\Resources\MessageTemplates\MessageTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMessageTemplate extends CreateRecord
{
    protected static string $resource = MessageTemplateResource::class;
}
