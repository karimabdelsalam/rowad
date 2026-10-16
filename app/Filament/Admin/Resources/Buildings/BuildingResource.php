<?php

namespace App\Filament\Admin\Resources\Buildings;

use App\Filament\Admin\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Admin\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Admin\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Admin\Resources\Buildings\Pages\ViewBuilding;
use App\Filament\Admin\Resources\Buildings\Schemas\BuildingForm;
use App\Filament\Admin\Resources\Buildings\Schemas\BuildingInfolist;
use App\Filament\Admin\Resources\Buildings\Tables\BuildingsTable;
use App\Filament\Resource;
use App\Models\Building;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'العقارات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مبنى';

    protected static ?string $pluralModelLabel = 'المباني والمجمعات';

    protected static ?string $permission = 'buildings';

    public static function form(Schema $schema): Schema
    {
        return BuildingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BuildingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BuildingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuildings::route('/'),
            'create' => CreateBuilding::route('/create'),
            'view' => ViewBuilding::route('/{record}'),
            'edit' => EditBuilding::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'number', 'plot_number', 'street'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('الرقم') => $record->number, __('الشارع') => $record->street]);
    }
}
