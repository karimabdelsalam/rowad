<?php

namespace App\Filament\Admin\Resources\RentContracts\Schemas;

use App\Enums\Calendar;
use App\Enums\ContractStatus;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\UnitUsage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RentContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number'),
                Select::make('unit_id')
                    ->relationship('unit', 'title')
                    ->required(),
                Select::make('employee_id')
                    ->relationship('employee', 'id'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                DatePicker::make('contract_date')
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Select::make('period_unit')
                    ->options(RentPeriodUnit::class)
                    ->required(),
                TextInput::make('period_count')
                    ->required()
                    ->numeric(),
                Select::make('payment_cycle')
                    ->options(PaymentCycle::class)
                    ->required(),
                TextInput::make('rent_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('yearly_rent')
                    ->numeric(),
                TextInput::make('commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('commission_cycle')
                    ->options(PaymentCycle::class),
                Toggle::make('vat_applicable')
                    ->required(),
                TextInput::make('vat_percent')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('vat_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('usage')
                    ->options(UnitUsage::class),
                Textarea::make('meter_readings')
                    ->columnSpanFull(),
                Textarea::make('extra_terms')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DatePicker::make('next_payment_date'),
                TextInput::make('next_payment_amount')
                    ->numeric(),
                Select::make('status')
                    ->options(ContractStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('ended_at'),
                DateTimePicker::make('cancelled_at'),
                DateTimePicker::make('archived_at'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
