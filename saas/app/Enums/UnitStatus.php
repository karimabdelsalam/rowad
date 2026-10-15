<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnitStatus: string implements HasLabel
{
    case Available = 'available';
    case Rented = 'rented';
    case Sold = 'sold';
    case Reserved = 'reserved';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Available => 'متاح',
            self::Rented => 'مؤجر',
            self::Sold => 'مباع',
            self::Reserved => 'محجوز',
        });
    }
}
