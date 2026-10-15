<?php

namespace App\Filament\Admin\Resources\RentContracts\Schemas;

use App\Models\RentContract;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RentContractInfolist
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
                TextEntry::make('start_date')
                    ->date(),
                TextEntry::make('end_date')
                    ->date(),
                TextEntry::make('period_unit')
                    ->badge(),
                TextEntry::make('period_count')
                    ->numeric(),
                TextEntry::make('payment_cycle')
                    ->badge(),
                TextEntry::make('rent_amount')
                    ->numeric(),
                TextEntry::make('yearly_rent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('commission')
                    ->numeric(),
                TextEntry::make('commission_cycle')
                    ->badge()
                    ->placeholder('-'),
                IconEntry::make('vat_applicable')
                    ->boolean(),
                TextEntry::make('vat_percent')
                    ->numeric(),
                TextEntry::make('vat_amount')
                    ->numeric(),
                TextEntry::make('usage')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('meter_readings')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('extra_terms')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('next_payment_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('next_payment_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('ended_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancelled_at')
                    ->dateTime()
                    ->placeholder('-'),
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
                    ->visible(fn (RentContract $record): bool => $record->trashed()),
            ]);
    }
}
