<?php

namespace App\Filament\Admin\Clusters\Lookups;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Legacy "multiadd" reference lists: cities, districts, categories, banks, types... */
class LookupsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('القوائم المرجعية');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('القوائم المرجعية');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('lookups.view_any'));
    }
}
