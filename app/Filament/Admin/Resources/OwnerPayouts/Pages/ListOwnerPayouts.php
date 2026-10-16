<?php

namespace App\Filament\Admin\Resources\OwnerPayouts\Pages;

use App\Filament\Admin\Resources\OwnerPayouts\OwnerPayoutResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOwnerPayouts extends ListRecords
{
    protected static string $resource = OwnerPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label(__('دفعة توزيع جديدة'))->icon(Heroicon::OutlinedPlus)
                ->visible(fn () => OwnerPayoutResource::canCreate())
                ->url(OwnerPayoutResource::getUrl('prepare')),
        ];
    }
}
