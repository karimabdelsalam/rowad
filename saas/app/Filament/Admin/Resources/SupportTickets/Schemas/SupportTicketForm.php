<?php

namespace App\Filament\Admin\Resources\SupportTickets\Schemas;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                Select::make('type')
                    ->options(TicketType::class)
                    ->required(),
                Select::make('owner_id')
                    ->relationship('owner', 'id'),
                Select::make('client_id')
                    ->relationship('client', 'id'),
                Select::make('unit_id')
                    ->relationship('unit', 'title'),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(TicketStatus::class)
                    ->default('open')
                    ->required(),
                DateTimePicker::make('read_at'),
            ]);
    }
}
