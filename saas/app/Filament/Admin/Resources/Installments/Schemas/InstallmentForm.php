<?php

namespace App\Filament\Admin\Resources\Installments\Schemas;

use App\Enums\Calendar;
use App\Enums\InstallmentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InstallmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('unit_id')
                    ->relationship('unit', 'title')
                    ->required(),
                TextInput::make('contract_type')
                    ->required(),
                TextInput::make('contract_id')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->options(InstallmentType::class)
                    ->required(),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                DatePicker::make('due_date')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('seller_commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('buyer_commission')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('commission_type')
                    ->required()
                    ->default('money'),
                TextInput::make('vat_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('vat_percent')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('paid_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_collected')
                    ->required(),
                DateTimePicker::make('collected_at'),
                TextInput::make('reminder_stage')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('receipt_voucher_id')
                    ->relationship('receiptVoucher', 'id'),
                Select::make('payment_voucher_id')
                    ->relationship('paymentVoucher', 'id'),
                Select::make('invoice_id')
                    ->relationship('invoice', 'id'),
                Textarea::make('reason')
                    ->columnSpanFull(),
            ]);
    }
}
