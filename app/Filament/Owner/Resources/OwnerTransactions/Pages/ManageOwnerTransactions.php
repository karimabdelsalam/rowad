<?php

namespace App\Filament\Owner\Resources\OwnerTransactions\Pages;

use App\Filament\Owner\Resources\OwnerTransactions\OwnerTransactionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOwnerTransactions extends ManageRecords
{
    protected static string $resource = OwnerTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('print')
                ->label(__('طباعة كشف الحساب'))
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                ->url(fn () => route('documents.print', ['type' => 'owner_statement', 'id' => \App\Support\Portal::owner()->getKey()]))
                ->openUrlInNewTab(),
            \Filament\Actions\Action::make('pdf')->label('PDF')->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn () => route('documents.print', ['type' => 'owner_statement', 'id' => \App\Support\Portal::owner()->getKey(), 'pdf' => 1]))
                ->openUrlInNewTab(),
        ];
    }
}
