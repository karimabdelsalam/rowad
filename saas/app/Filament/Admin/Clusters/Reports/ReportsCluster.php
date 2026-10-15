<?php

namespace App\Filament\Admin\Clusters\Reports;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Legacy reports/* modules. */
class ReportsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    public static function getNavigationLabel(): string
    {
        return __('التقارير');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('التقارير');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || collect(config('rowad.permissions.reports'))->contains(fn ($record) => $user->can("reports.{$record}")));
    }
}
