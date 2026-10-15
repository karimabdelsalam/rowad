<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IdType: string implements HasLabel
{
    case NationalId = 'national_id';
    case Iqama = 'iqama';
    case Passport = 'passport';
    case GccId = 'gcc_id';
    case CommercialRegister = 'commercial_register';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::NationalId => 'هوية وطنية',
            self::Iqama => 'إقامة',
            self::Passport => 'جواز سفر',
            self::GccId => 'هوية خليجية',
            self::CommercialRegister => 'سجل تجاري',
        });
    }
}
