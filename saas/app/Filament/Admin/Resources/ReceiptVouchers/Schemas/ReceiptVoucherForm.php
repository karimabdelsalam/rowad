<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Schemas;

use App\Enums\Calendar;
use App\Enums\PaymentMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReceiptVoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                DatePicker::make('issued_at')
                    ->required(),
                TextInput::make('party_type'),
                TextInput::make('party_id')
                    ->numeric(),
                TextInput::make('party_name'),
                Select::make('unit_id')
                    ->relationship('unit', 'title'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('vat_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('payment_method')
                    ->options(PaymentMethod::class)
                    ->default('cash')
                    ->required(),
                TextInput::make('bank_name'),
                TextInput::make('cheque_number'),
                DatePicker::make('cheque_date'),
                Select::make('bank_account_id')
                    ->relationship('bankAccount', 'id'),
                Select::make('transaction_type_id')
                    ->relationship('transactionType', 'title'),
                TextInput::make('reason'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
