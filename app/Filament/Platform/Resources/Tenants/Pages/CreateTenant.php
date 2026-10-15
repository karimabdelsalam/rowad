<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Services\Platform\OfficeProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OfficeProvisioner::class)->provision($data);
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
