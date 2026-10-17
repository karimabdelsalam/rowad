<?php

namespace App\Filament\Admin\Resources\MaintenanceRequests\Pages;

use App\Filament\Admin\Resources\MaintenanceRequests\MaintenanceActions;
use App\Filament\Admin\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceRequest extends ViewRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaintenanceActions::schedule(),
            MaintenanceActions::start(),
            MaintenanceActions::complete(),
            ...\App\Filament\Support\DocumentActions::make('maintenance_request', __('أمر الصيانة')),
            ActionGroup::make([
                EditAction::make()->modalWidth('2xl')
                    ->visible(fn (MaintenanceRequest $record) => $record->status->isOpen() && MaintenanceActions::can('update')),
                MaintenanceActions::cancel(),
            ])->button()->label(__('المزيد'))->color('gray'),
        ];
    }
}
