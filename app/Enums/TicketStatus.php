<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TicketStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Open => 'مفتوحة',
            self::Answered => 'تم الرد',
            self::Closed => 'مغلقة',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Answered => 'info',
            self::Closed => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Open => Heroicon::OutlinedEnvelope,
            self::Answered => Heroicon::OutlinedChatBubbleLeftRight,
            self::Closed => Heroicon::OutlinedLockClosed,
        };
    }
}
