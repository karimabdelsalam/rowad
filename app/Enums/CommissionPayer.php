<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommissionPayer: string implements HasLabel
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Split = 'split';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Buyer => 'المشتري',
            self::Seller => 'البائع',
            self::Split => 'مناصفة',
        });
    }
}
