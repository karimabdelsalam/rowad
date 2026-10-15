<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/** Office dashboard on a 12-column grid: banner, KPIs, charts side by side, then work lists. */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }
}
