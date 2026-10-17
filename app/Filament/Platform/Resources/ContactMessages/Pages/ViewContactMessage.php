<?php

namespace App\Filament\Platform\Resources\ContactMessages\Pages;

use App\Filament\Platform\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [ContactMessageResource::handleAction()];
    }
}
