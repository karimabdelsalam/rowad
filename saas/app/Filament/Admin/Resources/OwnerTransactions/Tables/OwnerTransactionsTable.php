<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OwnerTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner.id')
                    ->searchable(),
                TextColumn::make('unit.title')
                    ->searchable(),
                TextColumn::make('transaction_type_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('installment.id')
                    ->searchable(),
                TextColumn::make('contract_type')
                    ->searchable(),
                TextColumn::make('contract_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('direction')
                    ->badge()
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('vat_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_settled')
                    ->boolean(),
                TextColumn::make('settled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('receiptVoucher.id')
                    ->searchable(),
                TextColumn::make('paymentVoucher.id')
                    ->searchable(),
                TextColumn::make('parent.id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
