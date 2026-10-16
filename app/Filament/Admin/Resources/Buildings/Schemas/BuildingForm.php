<?php

namespace App\Filament\Admin\Resources\Buildings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                \Filament\Forms\Components\Select::make('branch_id')->label(__('الفرع'))
                    ->options(fn () => \App\Support\Branches::options())
                    ->default(fn () => \App\Support\Branches::current())
                    ->disabled(fn () => \App\Support\Branches::current() !== null)->dehydrated()
                    ->visible(fn () => \App\Support\Branches::inUse()),
                TextInput::make('property_category_id')
                    ->numeric(),
                TextInput::make('number'),
                Select::make('city_id')
                    ->relationship('city', 'name'),
                Select::make('district_id')
                    ->relationship('district', 'name'),
                TextInput::make('street'),
                TextInput::make('zone'),
                TextInput::make('plan_number'),
                TextInput::make('plot_number'),
                TextInput::make('area')
                    ->numeric(),
                TextInput::make('floors')
                    ->numeric(),
                TextInput::make('address'),
                Textarea::make('address_details')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
            ]);
    }
}
