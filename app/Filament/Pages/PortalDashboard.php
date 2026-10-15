<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/** Owner and client portal home: banner, figures and the next payments, one per row. */
class PortalDashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return 1;
    }
}
