<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Where a rent contract stands on the Ejar platform. */
enum EjarStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case AwaitingParties = 'awaiting_parties';
    case Documented = 'documented';
    case NotRequired = 'not_required';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Pending => 'غير موثق',
            self::AwaitingParties => 'بانتظار موافقة الأطراف',
            self::Documented => 'موثق في إيجار',
            self::NotRequired => 'لا يلزم التوثيق',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'danger',
            self::AwaitingParties => 'warning',
            self::Documented => 'success',
            self::NotRequired => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedExclamationTriangle,
            self::AwaitingParties => Heroicon::OutlinedClock,
            self::Documented => Heroicon::OutlinedShieldCheck,
            self::NotRequired => Heroicon::OutlinedMinusCircle,
        };
    }
}
