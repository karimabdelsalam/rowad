<?php

namespace App\Filament\Admin\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('father_name')
                    ->searchable(),
                TextColumn::make('grandfather_name')
                    ->searchable(),
                TextColumn::make('family_name')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('id_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('id_number')
                    ->searchable(),
                TextColumn::make('id_source')
                    ->searchable(),
                TextColumn::make('id_issue_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('nationality')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('home_phone')
                    ->searchable(),
                TextColumn::make('work')
                    ->searchable(),
                TextColumn::make('work_phone')
                    ->searchable(),
                TextColumn::make('id_expiry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('mobile2')
                    ->searchable(),
                TextColumn::make('education')
                    ->searchable(),
                TextColumn::make('marital_status')
                    ->searchable(),
                TextColumn::make('salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rent_commission')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sale_commission')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('calendar')
                    ->badge()
                    ->searchable(),
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
