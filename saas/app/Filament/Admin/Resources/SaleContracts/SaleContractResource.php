<?php

namespace App\Filament\Admin\Resources\SaleContracts;

use App\Filament\Admin\Resources\SaleContracts\Pages\CreateSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Pages\EditSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Pages\ListSaleContracts;
use App\Filament\Admin\Resources\SaleContracts\Pages\ViewSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Schemas\SaleContractForm;
use App\Filament\Admin\Resources\SaleContracts\Schemas\SaleContractInfolist;
use App\Filament\Admin\Resources\SaleContracts\Tables\SaleContractsTable;
use App\Models\SaleContract;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleContractResource extends Resource
{
    protected static ?string $model = SaleContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'العقود';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عقد بيع';

    protected static ?string $pluralModelLabel = 'عقود البيع';

    protected static ?string $permission = 'sale_contracts';

    public static function form(Schema $schema): Schema
    {
        return SaleContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SaleContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\RentContracts\RelationManagers\InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleContracts::route('/'),
            'create' => CreateSaleContract::route('/create'),
            'view' => ViewSaleContract::route('/{record}'),
            'edit' => EditSaleContract::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
