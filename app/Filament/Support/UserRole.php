<?php

namespace App\Filament\Support;

use App\Models\Branch;
use App\Models\User;
use App\Support\Branches;

/** What the signed-in user is in this panel, as shown under their name: "مدير النظام", "محاسب", "مالك"… */
final class UserRole
{
    private const ROLES = ['admin' => 'مدير', 'accountant' => 'محاسب', 'agent' => 'مسوّق', 'viewer' => 'مشاهد'];

    public static function label(?User $user): string
    {
        $panel = filament()->getCurrentPanel()?->getId();
        $label = match (true) {
            $panel === 'owner' => __('مالك'),
            $panel === 'client' => __('مستأجر / عميل'),
            $panel === 'platform' => __('إدارة المنصة'),
            $user?->is_super_admin ?? false => __('مدير النظام'),
            default => __(self::ROLES[$user?->roles?->first()?->name] ?? 'موظف'),
        };
        if ($panel === 'admin' && ($branchId = Branches::current()) !== null) {
            $label .= ' · '.Branch::query()->whereKey($branchId)->value('name');
        }

        return $label;
    }
}
