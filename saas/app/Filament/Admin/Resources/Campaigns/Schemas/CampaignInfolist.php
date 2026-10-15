<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('channel')
                    ->badge(),
                TextEntry::make('audience'),
                TextEntry::make('subject')
                    ->placeholder('-'),
                TextEntry::make('body')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('recipients_count')
                    ->numeric(),
                TextEntry::make('sent_count')
                    ->numeric(),
                TextEntry::make('failed_count')
                    ->numeric(),
                TextEntry::make('scheduled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
