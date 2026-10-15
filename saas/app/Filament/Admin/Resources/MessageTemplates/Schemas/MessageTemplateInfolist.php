<?php

namespace App\Filament\Admin\Resources\MessageTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('event'),
                TextEntry::make('title'),
                TextEntry::make('email_subject')
                    ->placeholder('-'),
                TextEntry::make('email_body')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('sms_body')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('email_enabled')
                    ->boolean(),
                IconEntry::make('sms_enabled')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
