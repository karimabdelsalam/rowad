<?php

namespace App\Filament\Admin\Resources\EmployeePenalties\Pages;

use App\Filament\Admin\Resources\EmployeePenalties\EmployeePenaltyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeePenalties extends ManageRecords
{
    protected static string $resource = EmployeePenaltyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
