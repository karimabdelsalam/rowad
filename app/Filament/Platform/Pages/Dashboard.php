<?php

namespace App\Filament\Platform\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/** Platform home on a 12-column grid: headline figures, growth and revenue charts, then the offices to call. */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }
}
