<?php

namespace App\Filament\Owner\Resources\MaintenanceRequests\Pages;

use App\Filament\Owner\Resources\MaintenanceRequests\MaintenanceRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenanceRequests extends ManageRecords
{
    protected static string $resource = MaintenanceRequestResource::class;
}
