<?php

namespace App\Filament\Owner\Resources\OwnerTransactions;

use App\Filament\Owner\Resources\OwnerTransactions\Pages\ManageOwnerTransactions;
use App\Filament\PortalResource;
use App\Models\OwnerTransaction;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The owner's account statement (legacy units/transactions). */
class OwnerTransactionResource extends PortalResource
{
    protected static ?string $model = OwnerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'حركة';

    protected static ?string $pluralModelLabel = 'كشف الحساب';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('owner_id', Portal::owner()->getKey())->with(['unit', 'type']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->description(fn () => __('الرصيد المستحق لك').': '.number_format(Portal::owner()->outstandingBalance(), 2))
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('type.title')->label(__('البيان')),
                TextColumn::make('direction')->badge()->color(fn ($state) => $state->value === 'debit' ? 'success' : 'danger'),
                TextColumn::make('amount')->numeric(2),
                IconColumn::make('is_settled')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_settled')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOwnerTransactions::route('/')];
    }
}
