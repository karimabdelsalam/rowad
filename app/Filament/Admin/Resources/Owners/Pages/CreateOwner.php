<?php

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOwner extends CreateRecord
{
    protected static string $resource = OwnerResource::class;
}
