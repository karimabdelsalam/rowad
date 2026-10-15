<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel
{
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Active => 'ساري',
            self::Ended => 'منتهي',
            self::Cancelled => 'ملغي',
            self::Completed => 'مكتمل',
        });
    }
}
