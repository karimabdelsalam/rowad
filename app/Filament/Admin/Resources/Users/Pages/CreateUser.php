<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use \App\Filament\Concerns\EnforcesPlanQuota;

    protected static function planQuota(): string
    {
        return 'users';
    }

    protected static string $resource = UserResource::class;
}
