<?php

namespace App\Services\Hr;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;

/** Annual-leave entitlement and what is left of it this year (Saudi Labour Law art. 109). */
final class LeaveBalance
{
    public static function entitlement(Employee $employee, ?int $year = null): int
    {
        if ($employee->annual_leave_days !== null) {
            return (int) $employee->annual_leave_days;
        }
        $years = $employee->serviceYears(\Carbon\CarbonImmutable::create($year ?? now()->year, 12, 31));

        return $years >= HrSettings::int('senior_years') ? HrSettings::int('annual_leave_days_senior') : HrSettings::int('annual_leave_days');
    }

    public static function used(Employee $employee, ?int $year = null): int
    {
        $year ??= now()->year;

        return (int) $employee->leaves()->where('type', LeaveType::Annual)->where('status', LeaveStatus::Approved)
            ->whereYear('start_date', $year)->sum('days');
    }

    public static function remaining(Employee $employee, ?int $year = null): int
    {
        return self::entitlement($employee, $year) - self::used($employee, $year);
    }

    /** Sick leave this year by pay tier: 30 days full, 60 at 75%, 30 unpaid (art. 117). */
    public static function sickUsed(Employee $employee, ?int $year = null): int
    {
        return (int) $employee->leaves()->where('type', LeaveType::Sick)->where('status', LeaveStatus::Approved)
            ->whereYear('start_date', $year ?? now()->year)->sum('days');
    }
}
