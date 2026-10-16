<?php

namespace App\Services\Hr;

use App\Support\Settings;

/** Office-level HR parameters with the statutory Saudi defaults; editable in the general settings. */
final class HrSettings
{
    public const DEFAULTS = [
        'gosi_saudi_employee' => 9.75,     // annuities 9% + SANED 0.75%
        'gosi_saudi_employer' => 11.75,    // annuities 9% + occupational hazards 2% + SANED 0.75%
        'gosi_expat_employer' => 2.0,      // occupational hazards only
        'gosi_wage_cap' => 45000,          // contribution wage ceiling (basic + housing)
        'gosi_wage_floor' => 1500,
        'annual_leave_days' => 21,         // art. 109
        'annual_leave_days_senior' => 30,  // after five years of service
        'senior_years' => 5,
        'sick_full_days' => 30,            // art. 117: 30 full pay, 60 at 75%, 30 unpaid
        'sick_three_quarter_days' => 60,
        'month_days' => 30,                // daily wage = monthly wage / 30
        'overtime_rate' => 1.5,            // art. 107
        'payroll_day' => 27,
        'wps_establishment_id' => '',      // MOL establishment number for the WPS file
    ];

    public static function get(string $key): mixed
    {
        $value = Settings::get('hr_'.$key);

        return $value === null || $value === '' ? self::DEFAULTS[$key] : $value;
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }
}
