<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewRentContract extends ViewRecord
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Admin\Pages\Assistant::askAboutAction('rent_contract'),
            Action::make('print')
                ->label(__('طباعة العقد'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('documents.print', ['type' => 'rent_contract', 'id' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
