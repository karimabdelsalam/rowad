<?php

namespace App\Filament\Admin\Resources\Units\Pages;

use App\Filament\Admin\Resources\Units\UnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Admin\Pages\Assistant::askAboutAction('unit'),
            \Filament\Actions\Action::make('print')
                ->label(__('طباعة البطاقة'))
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('documents.print', ['type' => 'unit', 'id' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
