<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantActions;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantActions::recordPayment(),
            TenantActions::loginAs(),
            EditAction::make(),
            ActionGroup::make([
                TenantActions::open(),
                TenantActions::extendTrial(),
                TenantActions::changeSubdomain(),
                TenantActions::upgradeHandled(),
                TenantActions::suspend(),
                TenantActions::activate(),
            ]),
        ];
    }
}
