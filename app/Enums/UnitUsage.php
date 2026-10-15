<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnitUsage: string implements HasLabel
{
    case Residential = 'residential';
    case Commercial = 'commercial';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Residential => 'سكني',
            self::Commercial => 'تجاري',
        });
    }
}
