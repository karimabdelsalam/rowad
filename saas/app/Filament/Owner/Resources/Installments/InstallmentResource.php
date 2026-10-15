<?php

namespace App\Filament\Owner\Resources\Installments;

use App\Filament\Owner\Resources\Installments\Pages\ManageInstallments;
use App\Filament\PortalResource;
use App\Models\Installment;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Rent/installment collection status on the owner's units (legacy units/payments). */
class InstallmentResource extends PortalResource
{
    protected static ?string $model = Installment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'قسط';

    protected static ?string $pluralModelLabel = 'التحصيلات';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('unit.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with('unit');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2),
                TextColumn::make('paid_amount')->numeric(2),
                IconColumn::make('is_collected')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_collected')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstallments::route('/')];
    }
}
