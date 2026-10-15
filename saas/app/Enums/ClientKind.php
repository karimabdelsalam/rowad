<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClientKind: string implements HasLabel
{
    case Individual = 'individual';
    case Company = 'company';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Individual => 'فرد',
            self::Company => 'شركة / مؤسسة',
        });
    }
}
