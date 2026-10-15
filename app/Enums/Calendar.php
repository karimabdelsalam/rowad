<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Calendar: string implements HasLabel
{
    case Gregorian = 'gregorian';
    case Hijri = 'hijri';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Gregorian => 'ميلادي',
            self::Hijri => 'هجري',
        });
    }
}
