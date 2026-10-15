<?php

namespace App\Filament\Admin\Resources\InboxMessages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InboxMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source')
                    ->required()
                    ->default('contact'),
                Select::make('unit_id')
                    ->relationship('unit', 'title'),
                TextInput::make('locale')
                    ->required()
                    ->default('ar'),
                TextInput::make('ip_address'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('mobile'),
                TextInput::make('subject'),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('read_at'),
                Textarea::make('reply')
                    ->columnSpanFull(),
                DateTimePicker::make('replied_at'),
            ]);
    }
}
