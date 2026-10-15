<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketType: string implements HasLabel
{
    case Request = 'request';
    case Enquiry = 'enquiry';
    case Problem = 'problem';
    case Suggestion = 'suggestion';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Request => 'طلب',
            self::Enquiry => 'استفسار',
            self::Problem => 'مشكلة',
            self::Suggestion => 'اقتراح',
        });
    }
}
