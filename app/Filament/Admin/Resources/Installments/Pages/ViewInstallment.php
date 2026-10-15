<?php

namespace App\Filament\Admin\Resources\Installments\Pages;

use App\Filament\Admin\Resources\Installments\InstallmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallment extends ViewRecord
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
