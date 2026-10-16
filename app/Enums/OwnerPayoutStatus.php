<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum OwnerPayoutStatus: string implements HasColor, HasIcon, HasLabel
{
    case Prepared = 'prepared';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Prepared => 'بانتظار التحويل',
            self::Transferred => 'تم التحويل',
            self::Cancelled => 'ملغاة',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prepared => 'warning',
            self::Transferred => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Prepared => Heroicon::OutlinedClock,
            self::Transferred => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
