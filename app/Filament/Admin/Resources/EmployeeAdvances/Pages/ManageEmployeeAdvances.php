<?php

namespace App\Filament\Admin\Resources\EmployeeAdvances\Pages;

use App\Filament\Admin\Resources\EmployeeAdvances\EmployeeAdvanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeAdvances extends ManageRecords
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['remaining' => $data['amount'], 'created_by' => auth()->id()]),
        ];
    }
}
