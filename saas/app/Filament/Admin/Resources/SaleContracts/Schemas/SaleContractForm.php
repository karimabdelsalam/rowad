<?php

namespace App\Filament\Admin\Resources\SaleContracts\Schemas;

use App\Enums\Calendar;
use App\Enums\ContractStatus;
use App\Enums\SalePaymentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SaleContractForm
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
                TextInput::make('total_amount')
                    ->required()
                    ->numeric(),
                Select::make('payment_type')
                    ->options(SalePaymentType::class)
                    ->required(),
                Textarea::make('payment_plan')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('extra_terms')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ContractStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('archived_at'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
