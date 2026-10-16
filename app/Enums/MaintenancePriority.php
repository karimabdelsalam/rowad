<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenancePriority: string implements HasColor, HasLabel
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case Emergency = 'emergency';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Normal => 'عادي',
            self::Urgent => 'عاجل',
            self::Emergency => 'طارئ (تسريب/انقطاع)',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Urgent => 'warning',
            self::Emergency => 'danger',
        };
    }
}
