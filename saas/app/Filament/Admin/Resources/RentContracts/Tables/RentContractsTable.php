<?php

namespace App\Filament\Admin\Resources\RentContracts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable(),
                TextColumn::make('unit.title')
                    ->searchable(),
                TextColumn::make('employee.id')
                    ->searchable(),
                TextColumn::make('calendar')
                    ->badge()
                    ->searchable(),
                TextColumn::make('contract_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_unit')
                    ->badge()
                    ->searchable(),
                TextColumn::make('period_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payment_cycle')
                    ->badge()
                    ->searchable(),
                TextColumn::make('rent_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('yearly_rent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_cycle')
                    ->badge()
                    ->searchable(),
                IconColumn::make('vat_applicable')
                    ->boolean(),
                TextColumn::make('vat_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('vat_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('usage')
                    ->badge()
                    ->searchable(),
                TextColumn::make('next_payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('next_payment_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('cancelled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
