<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Owner ledger direction, from the office's books (legacy semantics):
 * debit  = amount the office owes the owner (owner's share of collected rent/installments),
 * credit = amount deducted from the owner (commission, VAT, taxes, management fee, expenses).
 */
enum LedgerDirection: string implements HasColor, HasIcon, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Credit => 'danger',
            self::Debit => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Credit => Heroicon::OutlinedArrowUpRight,
            self::Debit => Heroicon::OutlinedArrowDownLeft,
        };
    }
}
