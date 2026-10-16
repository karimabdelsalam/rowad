<?php

namespace App\Filament\Admin\Resources\Units;

use App\Filament\Admin\Resources\Units\Pages\CreateUnit;
use App\Filament\Admin\Resources\Units\Pages\EditUnit;
use App\Filament\Admin\Resources\Units\Pages\ListUnits;
use App\Filament\Admin\Resources\Units\Pages\ViewUnit;
use App\Filament\Admin\Resources\Units\Schemas\UnitForm;
use App\Filament\Admin\Resources\Units\Schemas\UnitInfolist;
use App\Filament\Admin\Resources\Units\Tables\UnitsTable;
use App\Filament\Resource;
use App\Models\Unit;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'العقارات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'وحدة';

    protected static ?string $pluralModelLabel = 'الوحدات';

    protected static ?string $permission = 'units';

    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'view' => ViewUnit::route('/{record}'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static array $globalSearchWith = ['building'];

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'number', 'deed_number', 'building.title'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title.($record->number ? ' · '.$record->number : '');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('العمارة') => $record->building?->title, __('الحالة') => $record->status?->getLabel()]);
    }
}
