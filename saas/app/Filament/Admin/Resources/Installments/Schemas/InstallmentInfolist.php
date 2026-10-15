<?php

namespace App\Filament\Admin\Resources\Installments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InstallmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('unit.title')
                    ->label('Unit'),
                TextEntry::make('contract_type'),
                TextEntry::make('contract_id')
                    ->numeric(),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('due_date')
                    ->date(),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('seller_commission')
                    ->numeric(),
                TextEntry::make('buyer_commission')
                    ->numeric(),
                TextEntry::make('commission_type'),
                TextEntry::make('vat_amount')
                    ->numeric(),
                TextEntry::make('vat_percent')
                    ->numeric(),
                TextEntry::make('paid_amount')
                    ->numeric(),
                IconEntry::make('is_collected')
                    ->boolean(),
                TextEntry::make('collected_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reminder_stage')
                    ->numeric(),
                TextEntry::make('receiptVoucher.id')
                    ->label('Receipt voucher')
                    ->placeholder('-'),
                TextEntry::make('paymentVoucher.id')
                    ->label('Payment voucher')
                    ->placeholder('-'),
                TextEntry::make('invoice.id')
                    ->label('Invoice')
                    ->placeholder('-'),
                TextEntry::make('reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
