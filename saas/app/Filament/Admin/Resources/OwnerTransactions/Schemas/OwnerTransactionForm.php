<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Schemas;

use App\Enums\LedgerDirection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OwnerTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('owner_id')
                    ->relationship('owner', 'id'),
                Select::make('unit_id')
                    ->relationship('unit', 'title'),
                TextInput::make('transaction_type_id')
                    ->required()
                    ->numeric(),
                Select::make('installment_id')
                    ->relationship('installment', 'id'),
                TextInput::make('contract_type'),
                TextInput::make('contract_id')
                    ->numeric(),
                TextInput::make('source'),
                Select::make('direction')
                    ->options(LedgerDirection::class)
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('vat_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('due_date')
                    ->required(),
                Toggle::make('is_settled')
                    ->required(),
                DateTimePicker::make('settled_at'),
                Select::make('receipt_voucher_id')
                    ->relationship('receiptVoucher', 'id'),
                Select::make('payment_voucher_id')
                    ->relationship('paymentVoucher', 'id'),
                Select::make('parent_id')
                    ->relationship('parent', 'id'),
            ]);
    }
}
