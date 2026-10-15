<?php

namespace App\Filament\Admin\Resources\Units\Tables;

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

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('building.title')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('offer_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('property_category_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clientCategory.title')
                    ->searchable(),
                TextColumn::make('number')
                    ->searchable(),
                TextColumn::make('floor')
                    ->searchable(),
                TextColumn::make('rooms')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('area')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_furnished')
                    ->boolean(),
                TextColumn::make('max_occupants')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deedType.title')
                    ->searchable(),
                TextColumn::make('deed_number')
                    ->searchable(),
                TextColumn::make('plan_number')
                    ->searchable(),
                TextColumn::make('plot_number')
                    ->searchable(),
                TextColumn::make('plots')
                    ->searchable(),
                TextColumn::make('zone')
                    ->searchable(),
                TextColumn::make('street')
                    ->searchable(),
                TextColumn::make('city.name')
                    ->searchable(),
                TextColumn::make('district.name')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rent_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_rent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_rent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rent_period')
                    ->badge()
                    ->searchable(),
                TextColumn::make('rent_payment_cycle')
                    ->badge()
                    ->searchable(),
                TextColumn::make('rent_commission')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sale_commission')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('vat_applicable')
                    ->boolean(),
                TextColumn::make('management_fee')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('management_fee_cycle')
                    ->badge()
                    ->searchable(),
                TextColumn::make('management_fee_start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('management_fee_next_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('calendar')
                    ->badge()
                    ->searchable(),
                TextColumn::make('current_contract_type')
                    ->searchable(),
                TextColumn::make('current_contract_id')
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
