<?php

namespace App\Filament\Admin\Resources\Units\Schemas;

use App\Enums\Calendar;
use App\Enums\OfferType;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\UnitStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('building_id')
                    ->relationship('building', 'title'),
                TextInput::make('title')
                    ->required(),
                Select::make('offer_type')
                    ->options(OfferType::class)
                    ->default('rent')
                    ->required(),
                Select::make('status')
                    ->options(UnitStatus::class)
                    ->default('available')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('property_category_id')
                    ->numeric(),
                Select::make('client_category_id')
                    ->relationship('clientCategory', 'title'),
                TextInput::make('number'),
                TextInput::make('floor'),
                TextInput::make('rooms')
                    ->numeric(),
                TextInput::make('area')
                    ->numeric(),
                Toggle::make('is_furnished')
                    ->required(),
                TextInput::make('max_occupants')
                    ->numeric(),
                Select::make('deed_type_id')
                    ->relationship('deedType', 'title'),
                TextInput::make('deed_number'),
                TextInput::make('plan_number'),
                TextInput::make('plot_number'),
                TextInput::make('plots'),
                TextInput::make('zone'),
                TextInput::make('street'),
                Select::make('city_id')
                    ->relationship('city', 'name'),
                Select::make('district_id')
                    ->relationship('district', 'name'),
                TextInput::make('address'),
                Textarea::make('address_details')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                Textarea::make('borders')
                    ->columnSpanFull(),
                Textarea::make('details')
                    ->columnSpanFull(),
                TextInput::make('rent_amount')
                    ->numeric(),
                TextInput::make('daily_rent')
                    ->numeric(),
                TextInput::make('monthly_rent')
                    ->numeric(),
                Select::make('rent_period')
                    ->options(RentPeriodUnit::class),
                Select::make('rent_payment_cycle')
                    ->options(PaymentCycle::class),
                TextInput::make('rent_commission')
                    ->numeric(),
                TextInput::make('sale_commission')
                    ->numeric(),
                Toggle::make('vat_applicable')
                    ->required(),
                TextInput::make('management_fee')
                    ->numeric(),
                Select::make('management_fee_cycle')
                    ->options(PaymentCycle::class),
                DatePicker::make('management_fee_start_date'),
                DatePicker::make('management_fee_next_date'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                TextInput::make('current_contract_type'),
                TextInput::make('current_contract_id')
                    ->numeric(),
            ]);
    }
}
