<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomFieldType: string implements HasLabel
{
    case Text = 'text';
    case Checkbox = 'checkbox';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Text => 'نص',
            self::Checkbox => 'اختيار (نعم/لا)',
            self::Number => 'رقم',
            self::Date => 'تاريخ',
            self::Select => 'قائمة اختيار',
        });
    }
}
