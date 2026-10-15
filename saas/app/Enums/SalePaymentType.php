<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalePaymentType: string implements HasLabel
{
    case Once = 'once';
    case Parts = 'parts';
    case Installment = 'installment';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Once => 'دفعة واحدة',
            self::Parts => 'دفعات',
            self::Installment => 'أقساط',
        });
    }
}
