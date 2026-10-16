<?php

namespace App\Services\Scheduling;

use App\Enums\ContractStatus;
use App\Enums\NotificationEvent as E;
use App\Enums\UnitStatus;
use App\Models\Employee;
use App\Models\GovernmentLicense;
use App\Models\Installment;
use App\Models\LoginActivity;
use App\Models\OtpCode;
use App\Models\RentContract;
use App\Models\Unit;
use App\Services\Contracts\ContractService;
use App\Services\Ledger\OwnerLedger;
use App\Services\Messaging\DomainNotifications;
use Illuminate\Database\Eloquent\Builder;

/** Port of legacy cron/daily.php + core::refreshContracts, run once a day inside each office. */
class DailyOfficeJobs
{
    public function __construct(
        private readonly DomainNotifications $notifications,
        private readonly ContractService $contracts,
        private readonly OwnerLedger $ledger,
    ) {
    }

    /** @return array<string, int> what was done, for the command output */
    public function run(): array
    {
        return [
            'reminders' => $this->installmentReminders(),
            'collection_ladder' => array_sum(app(\App\Services\Collection\CollectionEscalation::class)->run()),
            'viewing_reminders' => app(\App\Services\Viewings\ViewingSchedule::class)->sendReminders(),
            'employees' => $this->employeeIdExpiry(),
            'licenses' => $this->licenseExpiry(),
            'released_units' => $this->releaseEndedContracts(),
            'activated_contracts' => $this->contracts->activateStartedContracts(),
            'management_fees' => $this->chargeManagementFees(),
            'cleanup' => $this->cleanup(),
        ];
    }

    /**
     * Stages (legacy payments.alert): 0 → 15-day reminder (rent and sale) → 1-day reminder (sale)
     * → due-today reminder (sale) → daily overdue reminder while unpaid (sale).
     */
    public function installmentReminders(): int
    {
        $sent = 0;
        $open = fn () => Installment::query()->with(['unit', 'contract.clients'])
            ->where('is_collected', false)->where('type', '!=', 'expenses');

        $open()->where('reminder_stage', 0)->whereBetween('due_date', [today(), today()->addDays(15)])->each(function (Installment $i) use (&$sent) {
            $this->notifications->installmentReminder($i->type->isRent() ? E::RentDueIn15Days : E::SaleInstallmentDueIn15Days, $i);
            $i->forceFill(['reminder_stage' => 15, 'last_reminded_at' => now()])->save();
            $sent++;
        });

        $sale = fn () => $open()->where('contract_type', 'sale_contract');

        $sale()->where('reminder_stage', 15)->whereDate('due_date', today()->addDay())->each(function (Installment $i) use (&$sent) {
            $this->notifications->installmentReminder(E::SaleInstallmentDueTomorrow, $i);
            $i->forceFill(['reminder_stage' => 1, 'last_reminded_at' => now()])->save();
            $sent++;
        });

        $sale()->whereIn('reminder_stage', [0, 15, 1])->whereDate('due_date', today())->each(function (Installment $i) use (&$sent) {
            $this->notifications->installmentReminder(E::SaleInstallmentDueToday, $i);
            $i->forceFill(['reminder_stage' => 2, 'last_reminded_at' => now()])->save();
            $sent++;
        });

        $sale()->whereDate('due_date', '<', today())
            ->where(fn (Builder $q) => $q->whereNull('last_reminded_at')->orWhereDate('last_reminded_at', '<', today()))
            ->each(function (Installment $i) use (&$sent) {
                $this->notifications->installmentReminder(E::SaleInstallmentOverdue, $i);
                $i->forceFill(['reminder_stage' => 2, 'last_reminded_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    public function employeeIdExpiry(): int
    {
        $count = 0;
        Employee::whereNull('id_expiry_notified_at')->whereNotNull('id_expiry_date')
            ->whereDate('id_expiry_date', '<=', today()->addDays(15))
            ->each(function (Employee $employee) use (&$count) {
                $this->notifications->employeeIdExpiring($employee);
                $employee->forceFill(['id_expiry_notified_at' => now()])->saveQuietly();
                $count++;
            });

        return $count;
    }

    public function licenseExpiry(): int
    {
        $count = 0;
        GovernmentLicense::where('notify_before_expiry', true)->whereNull('expiry_notified_at')->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today()->addDays(15))
            ->each(function (GovernmentLicense $license) use (&$count) {
                $this->notifications->licenseExpiring($license);
                $license->forceFill(['expiry_notified_at' => now()])->saveQuietly();
                $count++;
            });

        return $count;
    }

    /** Legacy refreshContracts: units of contracts past their end date become available. */
    public function releaseEndedContracts(): int
    {
        $count = 0;
        Unit::where('current_contract_type', 'rent_contract')->with('currentContract')->each(function (Unit $unit) use (&$count) {
            $contract = $unit->currentContract;
            if ($contract instanceof RentContract && $contract->end_date->lt(today())) {
                $unit->forceFill(['status' => UnitStatus::Available, 'current_contract_type' => null, 'current_contract_id' => null])->save();
                $count++;
            }
        });

        return $count;
    }

    /** Legacy refreshContracts: charge every management fee that has fallen due (catching up missed periods). */
    public function chargeManagementFees(): int
    {
        $count = 0;
        Unit::where('is_active', true)->whereNotNull('management_fee')->where('management_fee', '>', 0)
            ->whereNotNull('management_fee_cycle')
            ->where(fn (Builder $q) => $q->whereDate('management_fee_next_date', '<=', today())
                ->orWhere(fn (Builder $q) => $q->whereNull('management_fee_next_date')->whereDate('management_fee_start_date', '<=', today())
                    ->whereDoesntHave('ownerTransactions', fn (Builder $q) => $q->whereHas('type', fn ($q) => $q->where('code', 'mancost')))))
            ->each(function (Unit $unit) use (&$count) {
                for ($guard = 0; $guard < 120; $guard++) {
                    $next = $this->ledger->chargeManagementFee($unit->refresh());
                    $count++;
                    if ($next === null || $next->greaterThan(today())) {
                        break;
                    }
                }
            });

        return $count;
    }

    private function cleanup(): int
    {
        return LoginActivity::where('created_at', '<', now()->subDays(180))->delete()
            + OtpCode::where('expires_at', '<', now()->subDay())->delete();
    }
}
