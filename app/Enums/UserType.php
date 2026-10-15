<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case Staff = 'staff';
    case Owner = 'owner';
    case Client = 'client';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Staff => 'موظف إدارة',
            self::Owner => 'مالك',
            self::Client => 'عميل',
        });
    }
}
