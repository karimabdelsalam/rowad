<?php

namespace App\Services\Hr;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\PayrollStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Finance\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll: build the lines from the employees' pay and the month's penalties, advances,
 * unpaid leave and GOSI; let HR adjust overtime/bonus/deductions; approve; then pay — one payment
 * voucher per employee and a Wage Protection System (WPS) file for the bank.
 */
class Payroll
{
    public function __construct(private readonly VoucherService $vouchers) {}

    public function generate(int $year, int $month): PayrollRun
    {
        if (PayrollRun::where(['year' => $year, 'month' => $month])->exists()) {
            throw new BusinessRuleException(__('مسيّر رواتب شهر :m/:y موجود بالفعل', ['m' => $month, 'y' => $year]));
        }

        return DB::transaction(function () use ($year, $month) {
            $run = PayrollRun::create(['year' => $year, 'month' => $month, 'created_by' => auth()->id()]);
            $start = CarbonImmutable::create($year, $month, 1);
            $end = $start->endOfMonth();

            Employee::query()->active()->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $end))->each(function (Employee $e) use ($run, $year, $month, $start, $end) {
                $gosi = Gosi::contributions($e);
                $dailyWage = $e->totalWage() / HrSettings::int('month_days');
                $unpaidDays = $e->leaves()->where('type', LeaveType::Unpaid)->where('status', LeaveStatus::Approved)
                    ->where('start_date', '<=', $end)->where('end_date', '>=', $start)->get()
                    ->sum(fn ($leave) => $leave->daysInMonth($year, $month));
                $penalties = (float) $e->penalties()->where('for_year', $year)->where('for_month', $month)->sum('amount');
                $advances = $e->advances()->where('remaining', '>', 0)->get()
                    ->sum(fn (EmployeeAdvance $a) => min((float) $a->installment_amount, (float) $a->remaining));

                PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $e->id,
                    'basic' => $e->salary,
                    'housing' => $e->housing_allowance,
                    'transport' => $e->transport_allowance,
                    'other_allowances' => $e->other_allowances,
                    'penalties' => $penalties,
                    'advances' => round($advances, 2),
                    'unpaid_leave_days' => $unpaidDays,
                    'unpaid_leave' => round($unpaidDays * $dailyWage, 2),
                    'gosi_employee' => $gosi['employee'],
                    'gosi_employer' => $gosi['employer'],
                ]);
            });
            $run->refreshTotals();

            return $run;
        });
    }

    public function approve(PayrollRun $run): void
    {
        if ($run->status !== PayrollStatus::Draft) {
            throw new BusinessRuleException(__('المسيّر معتمد بالفعل'));
        }
        if ($run->items()->doesntExist()) {
            throw new BusinessRuleException(__('المسيّر فارغ'));
        }
        $run->refreshTotals();
        $run->forceFill(['status' => PayrollStatus::Approved, 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
    }

    public function reopen(PayrollRun $run): void
    {
        if ($run->status !== PayrollStatus::Approved) {
            throw new BusinessRuleException(__('لا يمكن إعادة فتح مسيّر مصروف'));
        }
        $run->forceFill(['status' => PayrollStatus::Draft, 'approved_by' => null, 'approved_at' => null])->save();
    }

    /** Pay every line: a payment voucher per employee (from the chosen bank account), advances recovered, run marked paid. */
    public function pay(PayrollRun $run, ?BankAccount $account, string $method = 'transfer'): int
    {
        if ($run->status !== PayrollStatus::Approved) {
            throw new BusinessRuleException(__('اعتمد المسيّر أولًا'));
        }
        $count = 0;
        DB::transaction(function () use ($run, $account, $method, &$count) {
            foreach ($run->items()->with('employee')->get() as $item) {
                if ($item->payment_voucher_id !== null || (float) $item->net <= 0) {
                    continue;
                }
                $voucher = $this->vouchers->createPayment([
                    'party_type' => 'employee',
                    'party_id' => $item->employee_id,
                    'party_name' => $item->employee->full_name,
                    'amount' => (float) $item->net,
                    'payment_method' => $method,
                    'bank_account_id' => $account?->id,
                    'issued_at' => now(),
                    'reason' => __('راتب شهر :period', ['period' => $run->periodLabel()]),
                ]);
                $item->forceFill(['payment_voucher_id' => $voucher->id])->save();
                $this->recoverAdvances($item);
                $count++;
            }
            $run->forceFill(['status' => PayrollStatus::Paid, 'paid_at' => now(), 'bank_account_id' => $account?->id])->save();
        });

        return $count;
    }

    private function recoverAdvances(PayrollItem $item): void
    {
        $left = (float) $item->advances;
        foreach ($item->employee->advances()->where('remaining', '>', 0)->oldest('issued_at')->get() as $advance) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (float) $advance->remaining);
            $advance->forceFill(['remaining' => round((float) $advance->remaining - $take, 2)])->save();
            $left -= $take;
        }
    }

    /**
     * Wage Protection System salary file (SIF): the header and one record per employee, comma separated,
     * in the field order Saudi banks and Mudad accept. Amounts in riyals with two decimals.
     */
    public function wpsFile(PayrollRun $run, ?BankAccount $account = null): string
    {
        $account ??= $run->bankAccount;
        $items = $run->items()->with(['employee.bank'])->get();
        $period = sprintf('%04d-%02d', $run->year, $run->month);
        $lines = [implode(',', [
            'SIF', (string) HrSettings::get('wps_establishment_id'), (string) ($account?->bank?->swift ?? ''),
            now()->format('Y-m-d'), now()->format('H:i'), $items->count(), number_format((float) $items->sum('net'), 2, '.', ''), $period,
        ])];
        foreach ($items as $i) {
            $e = $i->employee;
            $lines[] = implode(',', [
                'EDR', (string) ($e->gosi_number ?: $e->id_number ?: $e->id), (string) ($e->bank?->swift ?? ''), (string) $e->iban,
                sprintf('%04d-%02d-01', $run->year, $run->month), $period, number_format((float) $i->net, 2, '.', ''),
                number_format((float) $i->basic, 2, '.', ''), number_format((float) $i->housing, 2, '.', ''),
                number_format((float) $i->transport + (float) $i->other_allowances + (float) $i->overtime + (float) $i->bonus, 2, '.', ''),
                number_format($i->deductionsTotal(), 2, '.', ''), str_replace(',', ' ', (string) $e->full_name),
            ]);
        }

        return implode("\r\n", $lines)."\r\n";
    }
}
