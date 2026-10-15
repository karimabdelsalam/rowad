<?php

namespace App\Filament\Admin\Resources\InboxMessages\Pages;

use App\Filament\Admin\Resources\InboxMessages\InboxMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListInboxMessages extends ListRecords
{
    protected static string $resource = InboxMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
