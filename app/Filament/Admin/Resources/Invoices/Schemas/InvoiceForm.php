<?php

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Models\Client;
use App\Support\Settings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/** Manual invoice (legacy invoices::create with free items). Totals and ZATCA QR are computed on save. */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('العميل'))->columns(3)->schema([
                \App\Filament\Support\QuickCreate::client(Select::make('client_id')->relationship('client', 'full_name')->searchable()->preload())->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $client = $state ? Client::find($state) : null;
                        $set('customer_name', $client?->displayName());
                        $set('customer_vat_number', $client?->vat_number);
                    }),
                TextInput::make('customer_name')->required(),
                TextInput::make('customer_vat_number')->regex('/^3\d{13}3$/'),
            ]),
            Repeater::make('items')
                ->label(__('البنود'))
                ->columns(4)
                ->minItems(1)
                ->schema([
                    TextInput::make('description')->label(__('البيان'))->required()->columnSpan(2),
                    TextInput::make('quantity')->label(__('الكمية'))->numeric()->default(1)->minValue(0.01)->required(),
                    TextInput::make('unit_price')->label(__('السعر قبل الضريبة'))->numeric()->minValue(0)->required(),
                    TextInput::make('vat_percent')->label(__('نسبة الضريبة %'))->numeric()->default(fn () => Settings::get('vat_percent', 15)),
                ]),
            Textarea::make('notes')->rows(2),
        ]);
    }
}
