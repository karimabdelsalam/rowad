<?php

namespace App\Filament\Admin\Resources\LeaveRequests\Pages;

use App\Filament\Admin\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveRequests extends ManageRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('طلب إجازة'))->mutateDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()]),
        ];
    }
}
