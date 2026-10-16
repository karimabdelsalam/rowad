<?php

namespace App\Filament\Platform;

use Filament\Support\Contracts\HasLabel;

/** Sidebar groups of the platform console, in display order. */
enum Nav: string implements HasLabel
{
    case Offices = 'offices';
    case Billing = 'billing';
    case Platform = 'platform';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Offices => 'المؤسسات',
            self::Billing => 'الباقات والترخيص',
            self::Platform => 'المنصة',
        });
    }
}
