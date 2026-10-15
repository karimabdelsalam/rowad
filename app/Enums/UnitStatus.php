<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UnitStatus: string implements HasColor, HasIcon, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Rented => 'info',
            self::Sold => 'gray',
            self::Reserved => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Available => Heroicon::OutlinedKey,
            self::Rented => Heroicon::OutlinedHomeModern,
            self::Sold => Heroicon::OutlinedCheckBadge,
            self::Reserved => Heroicon::OutlinedClock,
        };
    }
}
