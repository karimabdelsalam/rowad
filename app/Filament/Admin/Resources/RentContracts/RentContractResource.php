<?php

namespace App\Filament\Admin\Resources\RentContracts;

use App\Filament\Admin\Resources\RentContracts\Pages\CreateRentContract;
use App\Filament\Admin\Resources\RentContracts\Pages\EditRentContract;
use App\Filament\Admin\Resources\RentContracts\Pages\ListRentContracts;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Admin\Resources\RentContracts\RelationManagers\InstallmentsRelationManager;
use App\Filament\Admin\Resources\RentContracts\Schemas\RentContractForm;
use App\Filament\Admin\Resources\RentContracts\Schemas\RentContractInfolist;
use App\Filament\Admin\Resources\RentContracts\Tables\RentContractsTable;
use App\Filament\Resource;
use App\Models\RentContract;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RentContractResource extends Resource
{
    protected static ?string $model = RentContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'العقود';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'عقد إيجار';

    protected static ?string $pluralModelLabel = 'عقود الإيجار';

    protected static ?string $permission = 'rent_contracts';

    public static function form(Schema $schema): Schema
    {
        return RentContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RentContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentContractsTable::configure($table);
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
            'index' => ListRentContracts::route('/'),
            'create' => CreateRentContract::route('/create'),
            'view' => ViewRentContract::route('/{record}'),
            'edit' => EditRentContract::route('/{record}/edit'),
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
        return __('عقد إيجار :number', ['number' => $record->number]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('الوحدة') => $record->unit?->title, __('المستأجر') => $record->clients->first()?->displayName(),
            __('ينتهي') => static::searchDate($record->end_date)]);
    }
}
