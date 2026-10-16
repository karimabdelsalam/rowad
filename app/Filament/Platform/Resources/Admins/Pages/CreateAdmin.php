<?php

namespace App\Filament\Platform\Resources\Admins\Pages;

use App\Filament\Platform\Resources\Admins\AdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['type' => 'staff', 'is_super_admin' => true, 'email_verified_at' => now()];
    }
}
