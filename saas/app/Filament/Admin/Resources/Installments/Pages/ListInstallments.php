<?php

namespace App\Filament\Admin\Resources\Installments\Pages;

use App\Filament\Admin\Resources\Installments\InstallmentResource;
use Filament\Resources\Pages\ListRecords;

class ListInstallments extends ListRecords
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
