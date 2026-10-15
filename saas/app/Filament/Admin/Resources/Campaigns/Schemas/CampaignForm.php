<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Enums\MessageChannel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('channel')
                    ->options(MessageChannel::class)
                    ->required(),
                TextInput::make('audience')
                    ->required(),
                TextInput::make('subject'),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                TextInput::make('recipients_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('sent_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('failed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('scheduled_at'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
