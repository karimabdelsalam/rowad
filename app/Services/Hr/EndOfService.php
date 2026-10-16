<?php

namespace App\Services\Hr;

use App\Enums\EndOfServiceReason;
use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * End-of-service award (مكافأة نهاية الخدمة), Saudi Labour Law:
 * art. 84 — half a month's wage per year for the first five years, a full month per year after;
 * art. 85 — on resignation: nothing under 2 years, one third from 2 to 5, two thirds from 5 to 10, all from 10;
 * art. 87 — full award when a woman resigns within six months of marriage or three months of childbirth;
 * art. 80 — no award on a dismissal for cause. Fractions of a year count pro rata.
 */
final class EndOfService
{
    /**
     * @return array{years: float, wage: float, first_five: float, after_five: float, full_award: float, share: float, award: float,
     *               leave_days: float, leave_amount: float, daily_wage: float}
     */
    public static function calculate(Employee $employee, CarbonImmutable|\DateTimeInterface $endDate, EndOfServiceReason $reason, ?float $lastWage = null, float $leaveDays = 0): array
    {
        $end = CarbonImmutable::instance($endDate);
        $years = $employee->serviceYears($end);
        $wage = $lastWage ?? $employee->totalWage();

        $firstFive = min($years, 5) * 0.5 * $wage;
        $afterFive = max($years - 5, 0) * 1.0 * $wage;
        $full = round($firstFive + $afterFive, 2);

        $share = match ($reason) {
            EndOfServiceReason::Resignation => match (true) {
                $years < 2 => 0.0,
                $years < 5 => 1 / 3,
                $years < 10 => 2 / 3,
                default => 1.0,
            },
            EndOfServiceReason::Article80 => 0.0,
            default => 1.0, // termination, contract end, retirement, death, force majeure, art. 87
        };
        $dailyWage = $wage / HrSettings::int('month_days');

        return [
            'years' => $years,
            'wage' => round($wage, 2),
            'first_five' => round($firstFive, 2),
            'after_five' => round($afterFive, 2),
            'full_award' => $full,
            'share' => round($share, 4),
            'award' => round($full * $share, 2),
            'leave_days' => $leaveDays,
            'daily_wage' => round($dailyWage, 2),
            'leave_amount' => round($leaveDays * $dailyWage, 2), // art. 111: unused leave is paid out
        ];
    }
}
