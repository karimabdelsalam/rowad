<?php

namespace App\Filament\Admin\Resources\SaleContracts;

use App\Filament\Admin\Resources\RentContracts\RelationManagers\InstallmentsRelationManager;
use App\Filament\Admin\Resources\SaleContracts\Pages\CreateSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Pages\EditSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Pages\ListSaleContracts;
use App\Filament\Admin\Resources\SaleContracts\Pages\ViewSaleContract;
use App\Filament\Admin\Resources\SaleContracts\Schemas\SaleContractForm;
use App\Filament\Admin\Resources\SaleContracts\Schemas\SaleContractInfolist;
use App\Filament\Admin\Resources\SaleContracts\Tables\SaleContractsTable;
use App\Filament\Resource;
use App\Models\SaleContract;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            InstallmentsRelationManager::class,
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

    protected static array $globalSearchWith = ['unit', 'clients'];

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'unit.title', 'clients.full_name', 'clients.company_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return __('عقد بيع :number', ['number' => $record->number]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('الوحدة') => $record->unit?->title, __('المشتري') => $record->clients->first()?->displayName(),
            __('المبلغ') => static::searchMoney($record->total_amount)]);
    }
}
