<?php

namespace App\Filament\Admin\Resources\PaymentVouchers\Schemas;

use App\Models\PaymentVoucher;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentVoucherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('issued_at')
                    ->date(),
                TextEntry::make('party_type')
                    ->placeholder('-'),
                TextEntry::make('party_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('party_name')
                    ->placeholder('-'),
                TextEntry::make('unit.title')
                    ->label('Unit')
                    ->placeholder('-'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('vat_amount')
                    ->numeric(),
                TextEntry::make('payment_method')
                    ->badge(),
                TextEntry::make('bank_name')
                    ->placeholder('-'),
                TextEntry::make('cheque_number')
                    ->placeholder('-'),
                TextEntry::make('cheque_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('bankAccount.id')
                    ->label('Bank account')
                    ->placeholder('-'),
                TextEntry::make('expenseType.title')
                    ->label('Expense type')
                    ->placeholder('-'),
                TextEntry::make('reason')
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
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (PaymentVoucher $record): bool => $record->trashed()),
            ]);
    }
}
