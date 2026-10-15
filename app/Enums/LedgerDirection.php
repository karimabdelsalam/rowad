<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Owner ledger direction, from the office's books (legacy semantics):
 * debit  = amount the office owes the owner (owner's share of collected rent/installments),
 * credit = amount deducted from the owner (commission, VAT, taxes, management fee, expenses).
 */
enum LedgerDirection: string implements HasLabel
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Credit => 'دائن (خصم على المالك)',
            self::Debit => 'مدين (مستحق للمالك)',
        });
    }
}
