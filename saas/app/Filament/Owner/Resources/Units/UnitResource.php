<?php

namespace App\Filament\Owner\Resources\Units;

use App\Enums\UnitStatus;
use App\Filament\Owner\Resources\Units\Pages\ManageUnits;
use App\Filament\PortalResource;
use App\Models\Unit;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ownerarea "my properties". */
class UnitResource extends PortalResource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'وحدة';

    protected static ?string $pluralModelLabel = 'وحداتي';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with(['building', 'city', 'owners' => fn ($q) => $q->whereKey(Portal::owner()->getKey())]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('title'),
            TextEntry::make('building.title'),
            TextEntry::make('status')->badge(),
            TextEntry::make('city.name'),
            TextEntry::make('address'),
            TextEntry::make('rooms'),
            TextEntry::make('area'),
            TextEntry::make('rent_amount')->numeric(2),
            TextEntry::make('my_share')->label(__('نسبة الملكية'))->state(fn (Unit $record) => $record->owners->first()?->pivot->share_percent.' %'),
            TextEntry::make('details')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('building.title'),
                TextColumn::make('status')->badge(),
                TextColumn::make('rent_amount')->numeric(2),
                TextColumn::make('my_share')->label(__('نسبة الملكية'))->state(fn (Unit $record) => $record->owners->first()?->pivot->share_percent)->suffix(' %'),
            ])
            ->filters([SelectFilter::make('status')->options(UnitStatus::class)])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
