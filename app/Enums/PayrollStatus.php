<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Draft => 'مسودة',
            self::Approved => 'معتمد',
            self::Paid => 'مصروف',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'info',
            self::Paid => 'success',
        };
    }
}
