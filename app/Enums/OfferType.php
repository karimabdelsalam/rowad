<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OfferType: string implements HasLabel
{
    case Rent = 'rent';
    case Sale = 'sale';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Rent => 'للإيجار',
            self::Sale => 'للبيع',
        });
    }
}
