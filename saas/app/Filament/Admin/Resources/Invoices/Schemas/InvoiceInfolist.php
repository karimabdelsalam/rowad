<?php

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number'),
                TextEntry::make('type'),
                TextEntry::make('client.id')
                    ->label('Client')
                    ->placeholder('-'),
                TextEntry::make('customer_name')
                    ->placeholder('-'),
                TextEntry::make('customer_vat_number')
                    ->placeholder('-'),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('issue_date')
                    ->date(),
                TextEntry::make('items')
                    ->columnSpanFull(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('vat_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('paid_amount')
                    ->numeric(),
                TextEntry::make('zatca_qr')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('zatca_uuid')
                    ->placeholder('-'),
                TextEntry::make('zatca_hash')
                    ->placeholder('-'),
                TextEntry::make('zatca_status')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
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
