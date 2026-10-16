<?php

namespace App\Filament\Admin\Resources;

use App\Models\Employee;
use Filament\Forms\Components\Select;

/** Bits shared by the HR resources. */
final class Hr
{
    public const GROUP = 'الموارد البشرية';

    public static function employeeSelect(string $name = 'employee_id', bool $activeOnly = true): Select
    {
        return Select::make($name)->label(__('الموظف'))->required()->searchable()->preload()
            ->options(fn () => Employee::query()->when($activeOnly, fn ($q) => $q->active())->orderBy('full_name')->pluck('full_name', 'id'));
    }

    public static function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can($permission));
    }

    /** @return array<int, string> */
    public static function months(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->locale(app()->getLocale())->translatedFormat('F')])->all();
    }
}
