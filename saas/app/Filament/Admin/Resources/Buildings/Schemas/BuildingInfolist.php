<?php

namespace App\Filament\Admin\Resources\Buildings\Schemas;

use App\Models\Building;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BuildingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('property_category_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('city.name')
                    ->label('City')
                    ->placeholder('-'),
                TextEntry::make('district.name')
                    ->label('District')
                    ->placeholder('-'),
                TextEntry::make('street')
                    ->placeholder('-'),
                TextEntry::make('zone')
                    ->placeholder('-'),
                TextEntry::make('plan_number')
                    ->placeholder('-'),
                TextEntry::make('plot_number')
                    ->placeholder('-'),
                TextEntry::make('area')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('floors')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-'),
                TextEntry::make('address_details')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('latitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('longitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Building $record): bool => $record->trashed()),
            ]);
    }
}
