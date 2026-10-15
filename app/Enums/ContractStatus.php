<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ContractStatus: string implements HasColor, HasIcon, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Ended => 'gray',
            self::Cancelled => 'danger',
            self::Completed => 'info',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Ended => Heroicon::OutlinedArchiveBox,
            self::Cancelled => Heroicon::OutlinedXCircle,
            self::Completed => Heroicon::OutlinedCheckBadge,
        };
    }
}
