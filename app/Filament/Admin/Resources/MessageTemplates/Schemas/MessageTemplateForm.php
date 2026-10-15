<?php

namespace App\Filament\Admin\Resources\MessageTemplates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('email_subject')
                    ->email(),
                Textarea::make('email_body')
                    ->columnSpanFull(),
                Textarea::make('sms_body')
                    ->columnSpanFull(),
                Toggle::make('email_enabled')
                    ->required(),
                Toggle::make('sms_enabled')
                    ->required(),
            ]);
    }
}
