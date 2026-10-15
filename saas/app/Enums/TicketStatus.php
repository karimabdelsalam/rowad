<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasLabel
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
}
