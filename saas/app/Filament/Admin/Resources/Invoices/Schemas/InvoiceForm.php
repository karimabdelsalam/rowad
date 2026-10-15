<?php

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Enums\Calendar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('simplified'),
                Select::make('client_id')
                    ->relationship('client', 'id'),
                TextInput::make('customer_name'),
                TextInput::make('customer_vat_number'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                DatePicker::make('issue_date')
                    ->required(),
                Textarea::make('items')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric(),
                TextInput::make('vat_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total')
                    ->required()
                    ->numeric(),
                TextInput::make('paid_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('zatca_qr')
                    ->columnSpanFull(),
                TextInput::make('zatca_uuid'),
                TextInput::make('zatca_hash'),
                TextInput::make('zatca_status'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
