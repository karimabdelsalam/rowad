<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasColor, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Staff => 'primary',
            self::Owner => 'success',
            self::Client => 'info',
        };
    }
}
