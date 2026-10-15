<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OfferType: string implements HasColor, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Rent => 'info',
            self::Sale => 'primary',
        };
    }
}
