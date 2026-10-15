<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentPeriodUnit: string implements HasLabel
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Day => 'يوم',
            self::Month => 'شهر',
            self::Year => 'سنة',
        });
    }
}
