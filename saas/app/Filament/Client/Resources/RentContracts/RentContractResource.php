<?php

namespace App\Filament\Client\Resources\RentContracts;

use App\Filament\Client\Resources\RentContracts\Pages\ManageRentContracts;
use App\Filament\PortalResource;
use App\Models\RentContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/rent: the client's rent contracts. */
class RentContractResource extends PortalResource
{
    protected static ?string $model = RentContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'عقد إيجار';

    protected static ?string $pluralModelLabel = 'عقود الإيجار';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('clients', fn (Builder $q) => $q->whereKey(Portal::client()->getKey()))
            ->with('unit');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('number'),
            TextEntry::make('unit.title'),
            TextEntry::make('start_date')->date('Y-m-d'),
            TextEntry::make('end_date')->date('Y-m-d'),
            TextEntry::make('payment_cycle')->badge(),
            TextEntry::make('rent_amount')->numeric(2),
            TextEntry::make('next_payment_date')->date('Y-m-d'),
            TextEntry::make('next_payment_amount')->numeric(2),
            TextEntry::make('status')->badge(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('unit.title'),
                TextColumn::make('start_date')->date('Y-m-d'),
                TextColumn::make('end_date')->date('Y-m-d'),
                TextColumn::make('rent_amount')->numeric(2),
                TextColumn::make('next_payment_date')->date('Y-m-d'),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentContracts::route('/')];
    }
}
