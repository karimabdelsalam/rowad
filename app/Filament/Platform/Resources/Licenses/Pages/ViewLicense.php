<?php

namespace App\Filament\Platform\Resources\Licenses\Pages;

use App\Filament\Platform\Resources\Licenses\LicenseResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLicense extends ViewRecord
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        $actions = LicenseResource::lifecycleActions();

        return [array_shift($actions), EditAction::make(), ActionGroup::make($actions)->label(__('إجراءات'))->button()->color('gray')];
    }
}
