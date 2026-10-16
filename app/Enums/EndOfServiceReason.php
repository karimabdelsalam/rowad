<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Why the employment ended — decides the share of the award (Saudi Labour Law articles 84, 85 and 87). */
enum EndOfServiceReason: string implements HasLabel
{
    case Resignation = 'resignation';
    case Termination = 'termination';
    case ContractEnd = 'contract_end';
    case Retirement = 'retirement';
    case Death = 'death';
    case MarriageOrBirth = 'marriage_or_birth';
    case ForceMajeure = 'force_majeure';
    case Article80 = 'article_80';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Resignation => 'استقالة',
            self::Termination => 'إنهاء من صاحب العمل',
            self::ContractEnd => 'انتهاء مدة العقد',
            self::Retirement => 'تقاعد',
            self::Death => 'وفاة',
            self::MarriageOrBirth => 'استقالة العاملة بسبب الزواج أو الولادة (م 87)',
            self::ForceMajeure => 'قوة قاهرة',
            self::Article80 => 'فصل بلا مكافأة (م 80)',
        });
    }
}
