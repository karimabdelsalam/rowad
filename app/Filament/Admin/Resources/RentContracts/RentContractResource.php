<?php

namespace App\Filament\Admin\Resources\RentContracts;

use App\Filament\Admin\Resources\RentContracts\Pages\CreateRentContract;
use App\Filament\Admin\Resources\RentContracts\Pages\EditRentContract;
use App\Filament\Admin\Resources\RentContracts\Pages\ListRentContracts;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Admin\Resources\RentContracts\Schemas\RentContractForm;
use App\Filament\Admin\Resources\RentContracts\Schemas\RentContractInfolist;
use App\Filament\Admin\Resources\RentContracts\Tables\RentContractsTable;
use App\Models\RentContract;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            \App\Filament\Admin\Resources\RentContracts\RelationManagers\InstallmentsRelationManager::class,
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
}
