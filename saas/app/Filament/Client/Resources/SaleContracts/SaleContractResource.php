<?php

namespace App\Filament\Client\Resources\SaleContracts;

use App\Filament\Client\Resources\SaleContracts\Pages\ManageSaleContracts;
use App\Filament\PortalResource;
use App\Models\SaleContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/sell: the client's purchase contracts. */
class SaleContractResource extends PortalResource
{
    protected static ?string $model = SaleContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عقد بيع';

    protected static ?string $pluralModelLabel = 'عقود الشراء';

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
            TextEntry::make('contract_date')->date('Y-m-d'),
            TextEntry::make('total_amount')->numeric(2),
            TextEntry::make('payment_type')->badge(),
            TextEntry::make('status')->badge(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('unit.title'),
                TextColumn::make('contract_date')->date('Y-m-d'),
                TextColumn::make('total_amount')->numeric(2),
                TextColumn::make('payment_type')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaleContracts::route('/')];
    }
}
