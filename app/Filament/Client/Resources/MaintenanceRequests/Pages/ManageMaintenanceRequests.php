<?php

namespace App\Filament\Client\Resources\MaintenanceRequests\Pages;

use App\Filament\Client\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Services\Maintenance\MaintenanceService;
use App\Support\Portal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenanceRequests extends ManageRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('طلب صيانة جديد'))
                ->modalHeading(__('طلب صيانة جديد'))
                ->createAnother(false)
                ->modalSubmitActionLabel(__('إرسال الطلب'))
                ->successNotificationTitle(__('استلمنا طلبك وسنتواصل معك لتحديد موعد'))
                ->using(fn (array $data) => app(MaintenanceService::class)->open($data, Portal::client())),
        ];
    }
}
