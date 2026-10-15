<?php

namespace App\Filament\Platform\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                Select::make('plan_id')
                    ->relationship('plan', 'id')
                    ->required(),
                TextInput::make('billing_cycle')
                    ->required()
                    ->default('monthly'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('SAR'),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('gateway'),
                TextInput::make('gateway_reference'),
            ]);
    }
}
