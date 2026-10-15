<?php

namespace App\Filament\Client\Resources\Installments;

use App\Filament\Client\Resources\Installments\Pages\ManageInstallments;
use App\Filament\PortalResource;
use App\Models\Installment;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/payments: what the client owes and has paid. */
class InstallmentResource extends PortalResource
{
    protected static ?string $model = Installment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'دفعاتي';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class],
                fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey(Portal::client()->getKey())))
            ->where('type', '!=', 'expenses')
            ->with('unit');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('amount')->numeric(2),
                TextColumn::make('paid_amount')->numeric(2),
                TextColumn::make('remaining')->label(__('المتبقي'))->state(fn (Installment $record) => $record->remainingAmount())->numeric(2),
                IconColumn::make('is_collected')->label(__('مسدد'))->boolean(),
            ])
            ->filters([TernaryFilter::make('is_collected')->label(__('مسدد'))->default(false)]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstallments::route('/')];
    }
}
