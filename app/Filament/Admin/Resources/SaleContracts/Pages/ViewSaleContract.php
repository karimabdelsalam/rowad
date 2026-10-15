<?php

namespace App\Filament\Admin\Resources\SaleContracts\Pages;

use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSaleContract extends ViewRecord
{
    protected static string $resource = SaleContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('طباعة العقد'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('documents.print', ['type' => 'sale_contract', 'id' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
