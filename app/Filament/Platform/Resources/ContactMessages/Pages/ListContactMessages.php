<?php

namespace App\Filament\Platform\Resources\ContactMessages\Pages;

use App\Filament\Platform\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;
}
