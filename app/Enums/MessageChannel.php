<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageChannel: string implements HasLabel
{
    case Mail = 'mail';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Mail => 'بريد إلكتروني',
            self::Sms => 'رسالة نصية',
            self::WhatsApp => 'واتساب',
        });
    }
}
