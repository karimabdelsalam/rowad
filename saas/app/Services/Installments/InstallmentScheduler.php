<?php

namespace App\Services\Installments;

use App\Enums\Calendar;
use App\Enums\CommissionPayer;
use App\Enums\InstallmentType;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\SalePaymentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Support\Hijri;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates the receivable schedule of a contract.
 * Port of legacy payments_model::rentContractSetup / sellContractSetup.
 */
class InstallmentScheduler
{
    /** Regenerate a contract's schedule (legacy checkEditStatus + setup). */
    public function schedule(RentContract|SaleContract $contract): Collection
    {
        $this->ensureEditable($contract);

        return DB::transaction(function () use ($contract) {
            $contract->installments()->where('type', '!=', InstallmentType::Expenses)->delete();

            $rows = $contract instanceof RentContract ? $this->planRent($contract) : $this->planSale($contract);

            return collect($rows)->map(fn (array $row) => $contract->installments()->create($row + [
                'unit_id' => $contract->unit_id,
                'calendar' => $contract->calendar,
            ]));
        });
    }

    /** Contracts with collected money or settled owner entries are locked. */
    public function ensureEditable(Model $contract): void
    {
        if (! $contract->exists) {
            return;
        }
        if ($contract->installments()->where('is_collected', true)->exists()) {
            throw new BusinessRuleException(__('لا يمكن التعديل على العقد في وجود مبالغ مالية مرحلة'));
        }
        if (OwnerTransaction::whereMorphedTo('contract', $contract)->where('is_settled', true)->exists()) {
            throw new BusinessRuleException(__('لا يمكن التعديل على العقد في وجود مستحقات مالية مرحلة'));
        }
    }

    /** @return list<array<string, mixed>> */
    public function planRent(RentContract $contract): array
    {
        $cycle = $contract->payment_cycle;
        $step = $cycle === PaymentCycle::Day ? 1 : $cycle->months();
        $type = match ($cycle) {
            PaymentCycle::Day => InstallmentType::DayRent,
            PaymentCycle::Month => $contract->period_unit === RentPeriodUnit::Year ? InstallmentType::YearRentMonthly : InstallmentType::MonthRent,
            PaymentCycle::Quarter => InstallmentType::YearRentQuarterly,
            PaymentCycle::HalfYear => InstallmentType::YearRentHalfYearly,
            PaymentCycle::Year => InstallmentType::YearRentYearly,
            PaymentCycle::Once => throw new BusinessRuleException(__('دورة الدفع غير صالحة لعقد الإيجار')),
        };

        $count = $contract->period_unit === RentPeriodUnit::Year
            ? (int) (($contract->period_count * 12) / $step)
            : (int) $contract->period_count;

        $commissionCycle = $contract->commission_cycle;
        $commissionStep = $commissionCycle === PaymentCycle::Day ? 1 : $commissionCycle?->months();
        $start = CarbonImmutable::parse($contract->start_date);

        $rows = [];
        $totalAmount = 0.0;
        $totalCommission = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dueDate = match (true) {
                $cycle === PaymentCycle::Day => $start->addDays($i * $step),
                $contract->calendar === Calendar::Hijri => Hijri::addMonths($start, $i * $step),
                default => $start->addMonthsNoOverflow($i * $step),
            };

            $commission = match (true) {
                $commissionCycle === null => 0.0,
                $commissionCycle === PaymentCycle::Once => $i === 0 ? (float) $contract->commission : 0.0,
                $commissionCycle === PaymentCycle::Day => (float) $contract->commission,
                default => (($i * $step) % $commissionStep) === 0 ? (float) $contract->commission : 0.0,
            };

            $amount = round((float) $contract->rent_amount + $commission, 2);
            $totalAmount += $amount;
            $totalCommission += $commission;

            $row = [
                'type' => $type,
                'due_date' => $dueDate->toDateString(),
                'amount' => $amount,
                'buyer_commission' => $commission,
                'seller_commission' => 0,
                'commission_type' => 'money',
                'vat_amount' => (float) $contract->vat_amount,
                'vat_percent' => (float) $contract->vat_percent,
            ];

            // Daily rent is billed as one installment on the last day (legacy alert=15 marker).
            if ($cycle === PaymentCycle::Day) {
                if ($i === $count - 1) {
                    $rows[] = array_merge($row, [
                        'amount' => round($totalAmount, 2),
                        'buyer_commission' => round($totalCommission, 2),
                        'reminder_stage' => 15,
                    ]);
                }

                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function planSale(SaleContract $contract): array
    {
        $plan = $contract->payment_plan;
        $total = (float) $contract->total_amount;

        return match ($contract->payment_type) {
            SalePaymentType::Once => [$this->onceRow($contract, $plan, $total)],
            SalePaymentType::Parts => $this->partsRows($plan, $total),
            SalePaymentType::Installment => $this->installmentRows($contract, $plan, $total),
        };
    }

    private function onceRow(SaleContract $contract, array $plan, float $total): array
    {
        $isMoney = ($plan['commission_type'] ?? 'money') === 'money';
        $commission = $isMoney ? (float) ($plan['commission'] ?? 0) : ($total / 100) * (float) ($plan['commission'] ?? 0);
        [$seller, $buyer] = $this->splitCommission($commission, $plan['commission_payer'] ?? null);

        return [
            'type' => InstallmentType::InstantSale,
            'due_date' => CarbonImmutable::parse($contract->contract_date)->toDateString(),
            'amount' => round($total + $buyer, 2),
            'seller_commission' => $seller,
            'buyer_commission' => $buyer,
            'commission_type' => $isMoney ? 'money' : 'percent',
        ];
    }

    private function partsRows(array $plan, float $total): array
    {
        $parts = $plan['parts'] ?? [];
        if (round(array_sum(array_column($parts, 'amount')), 2) !== round($total, 2)) {
            throw new BusinessRuleException(__('خطأ! اجمالي مبالغ الدفعات لا يساوي اجمالي قيمة العقد'));
        }

        return array_map(function (array $part) use ($plan) {
            [$seller, $buyer] = $this->splitCommission((float) ($part['commission'] ?? 0), $plan['commission_payer'] ?? null);
            $due = ! empty($part['deferred']) ? $part['due_date'] : $part['received_date'];

            return [
                'type' => InstallmentType::PartsSale,
                'due_date' => CarbonImmutable::parse($due)->toDateString(),
                'amount' => round((float) $part['amount'] + $buyer, 2),
                'seller_commission' => $seller,
                'buyer_commission' => $buyer,
                'commission_type' => 'money',
            ];
        }, $parts);
    }

    /** The down payment (first_payment) is settled at signing and is not scheduled, as in the legacy system. */
    private function installmentRows(SaleContract $contract, array $plan, float $total): array
    {
        $count = (int) $plan['count'];
        if ($count < 1) {
            throw new BusinessRuleException(__('عدد الأقساط غير صالح'));
        }
        $step = PaymentCycle::fromLegacy($plan['cycle'])->months();
        $amount = round(($total - (float) ($plan['first_payment'] ?? 0)) / $count, 2);
        $start = CarbonImmutable::parse($plan['start_date']);

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $due = $contract->calendar === Calendar::Hijri ? Hijri::addMonths($start, $i * $step) : $start->addMonthsNoOverflow($i * $step);
            $rows[] = [
                'type' => InstallmentType::InstallmentSale,
                'due_date' => $due->toDateString(),
                'amount' => $amount,
                'seller_commission' => 0,
                'buyer_commission' => 0,
                'commission_type' => 'money',
            ];
        }

        return $rows;
    }

    /** @return array{0: float, 1: float} [seller, buyer] */
    private function splitCommission(float $commission, CommissionPayer|string|null $payer): array
    {
        $payer = $payer instanceof CommissionPayer ? $payer : CommissionPayer::tryFrom((string) $payer);

        return match ($payer) {
            CommissionPayer::Buyer => [0.0, round($commission, 2)],
            CommissionPayer::Seller => [round($commission, 2), 0.0],
            CommissionPayer::Split => [round($commission / 2, 2), round($commission / 2, 2)],
            default => [0.0, 0.0],
        };
    }
}
