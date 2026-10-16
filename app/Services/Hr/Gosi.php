<?php

namespace App\Services\Hr;

use App\Models\Employee;

/**
 * GOSI contributions (التأمينات الاجتماعية). The contribution wage is basic + housing allowance,
 * between the floor and the ceiling; Saudis pay annuities + SANED and the employer adds hazards,
 * non-Saudis are covered for occupational hazards by the employer only.
 */
final class Gosi
{
    /** @return array{wage: float, employee: float, employer: float, employee_rate: float, employer_rate: float} */
    public static function contributions(Employee $employee, ?float $basic = null, ?float $housing = null): array
    {
        if (! $employee->gosi_subscribed) {
            return ['wage' => 0.0, 'employee' => 0.0, 'employer' => 0.0, 'employee_rate' => 0.0, 'employer_rate' => 0.0];
        }
        $wage = ($basic ?? (float) $employee->salary) + ($housing ?? (float) $employee->housing_allowance);
        $wage = min(max($wage, HrSettings::float('gosi_wage_floor')), HrSettings::float('gosi_wage_cap'));
        $saudi = $employee->isSaudi();
        $employeeRate = $saudi ? HrSettings::float('gosi_saudi_employee') : 0.0;
        $employerRate = $saudi ? HrSettings::float('gosi_saudi_employer') : HrSettings::float('gosi_expat_employer');

        return [
            'wage' => round($wage, 2),
            'employee' => round($wage * $employeeRate / 100, 2),
            'employer' => round($wage * $employerRate / 100, 2),
            'employee_rate' => $employeeRate,
            'employer_rate' => $employerRate,
        ];
    }
}
