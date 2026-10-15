<?php

namespace App\Filament\Admin\Resources\SaleContracts\Schemas;

use App\Models\SaleContract;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('unit.title')
                    ->label('Unit'),
                TextEntry::make('employee.id')
                    ->label('Employee')
                    ->placeholder('-'),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('contract_date')
                    ->date(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('payment_type')
                    ->badge(),
                TextEntry::make('payment_plan')
                    ->columnSpanFull(),
                TextEntry::make('extra_terms')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('archived_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (SaleContract $record): bool => $record->trashed()),
            ]);
    }
}
