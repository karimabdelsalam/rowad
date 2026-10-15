<?php

namespace App\Filament\Platform\Resources\Plans\Pages;

use App\Filament\Platform\Resources\Plans\PlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A plan in use stays (offices and their payment history point to it); switch it off instead.
            DeleteAction::make()->visible(fn () => ! $this->record->tenants()->exists() && ! $this->record->subscriptions()->exists()),
        ];
    }
}
