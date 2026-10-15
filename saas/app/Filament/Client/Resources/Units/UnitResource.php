<?php

namespace App\Filament\Client\Resources\Units;

use App\Filament\Client\Resources\Units\Pages\ManageUnits;
use App\Filament\PortalResource;
use App\Models\Unit;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/units: the units the client rents or bought. */
class UnitResource extends PortalResource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'وحدة';

    protected static ?string $pluralModelLabel = 'وحداتي';

    public static function getEloquentQuery(): Builder
    {
        return Portal::clientUnits(Portal::client())->with(['building', 'city']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('title'),
            TextEntry::make('building.title'),
            TextEntry::make('number'),
            TextEntry::make('floor'),
            TextEntry::make('city.name'),
            TextEntry::make('address'),
            TextEntry::make('rooms'),
            TextEntry::make('area'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('building.title'),
                TextColumn::make('number'),
                TextColumn::make('city.name'),
                TextColumn::make('offer_type')->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
