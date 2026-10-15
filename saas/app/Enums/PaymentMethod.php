<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
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
}
