<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Transfer = 'transfer';
    case Card = 'card';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Cash => 'نقدًا',
            self::Cheque => 'شيك',
            self::Transfer => 'تحويل بنكي',
            self::Card => 'شبكة / بطاقة',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Cheque => 'warning',
            self::Transfer => 'info',
            self::Card => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Cash => Heroicon::OutlinedBanknotes,
            self::Cheque => Heroicon::OutlinedDocumentText,
            self::Transfer => Heroicon::OutlinedBuildingLibrary,
            self::Card => Heroicon::OutlinedCreditCard,
        };
    }
}
