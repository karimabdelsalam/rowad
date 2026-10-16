<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ViewingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Booked = 'booked';
    case Done = 'done';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Booked => 'محجوز',
            self::Done => 'تمت المعاينة',
            self::NoShow => 'لم يحضر',
            self::Cancelled => 'ملغي',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Booked => 'info',
            self::Done => 'success',
            self::NoShow => 'warning',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Booked => Heroicon::OutlinedCalendarDays,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::NoShow => Heroicon::OutlinedUserMinus,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
