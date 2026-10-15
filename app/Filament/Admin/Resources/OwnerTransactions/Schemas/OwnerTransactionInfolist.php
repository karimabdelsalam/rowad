<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OwnerTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner.id')
                    ->label('Owner')
                    ->placeholder('-'),
                TextEntry::make('unit.title')
                    ->label('Unit')
                    ->placeholder('-'),
                TextEntry::make('transaction_type_id')
                    ->numeric(),
                TextEntry::make('installment.id')
                    ->label('Installment')
                    ->placeholder('-'),
                TextEntry::make('contract_type')
                    ->placeholder('-'),
                TextEntry::make('contract_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('source')
                    ->placeholder('-'),
                TextEntry::make('direction')
                    ->badge(),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('vat_amount')
                    ->numeric(),
                TextEntry::make('due_date')
                    ->date(),
                IconEntry::make('is_settled')
                    ->boolean(),
                TextEntry::make('settled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('receiptVoucher.id')
                    ->label('Receipt voucher')
                    ->placeholder('-'),
                TextEntry::make('paymentVoucher.id')
                    ->label('Payment voucher')
                    ->placeholder('-'),
                TextEntry::make('parent.id')
                    ->label('Parent')
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
