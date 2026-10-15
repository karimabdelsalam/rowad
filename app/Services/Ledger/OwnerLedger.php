<?php

namespace App\Services\Ledger;

use App\Enums\InstallmentType;
use App\Enums\LedgerDirection;
use App\Enums\PaymentCycle;
use App\Enums\Calendar;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\Unit;
use App\Models\Lookups\TransactionType;
use App\Support\Hijri;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Splits money between the office and the unit owners.
 * Port of legacy transactions::collectPayment, transactions::collectStatement
 * and payments_model::buildManCostPeriod.
 */
class OwnerLedger
{
    /** @var array<string, int> */
    private array $typeIds = [];

    /** Owner shares of a collected installment (legacy collectPayment). */
    public function recordCollection(Installment $installment): void
    {
        if ($installment->type === InstallmentType::Expenses || $installment->ownerTransactions()->exists()) {
            return;
        }

        DB::transaction(function () use ($installment) {
            $unit = $installment->unit()->with('owners')->firstOrFail();
            $base = (float) $installment->amount - (float) $installment->buyer_commission;
            $vat = (float) $installment->vat_amount;
            $sellerCommission = (float) $installment->seller_commission;
            $common = [
                'unit_id' => $unit->id,
                'installment_id' => $installment->id,
                'contract_type' => $installment->contract_type,
                'contract_id' => $installment->contract_id,
                'source' => $installment->type->isRent() ? 'rent' : 'sell',
                'due_date' => $installment->due_date,
            ];

            foreach ($unit->owners as $owner) {
                $share = (float) $owner->pivot->share_percent;
                $expensesShare = (float) $owner->pivot->expenses_percent;
                $entry = $common + ['owner_id' => $owner->id];

                $total = round($base * $share / 100, 2);
                $this->add($entry, 'payment', LedgerDirection::Debit, $total);

                $tax = 0.0;
                if ($vat > 0) {
                    $tax = round($vat * $share / 100, 2);
                    $this->add($entry, 'tax', LedgerDirection::Credit, $tax);
                }

                $commission = 0.0;
                if ($expensesShare > 0 && $sellerCommission > 0) {
                    $commission = round($sellerCommission * $expensesShare / 100, 2);
                    $parent = $this->add($entry, 'commission', LedgerDirection::Credit, $commission);
                    $this->addIncomeTax($entry, $commission, $parent);
                }

                if (Settings::enabled('owner_tax_enabled')) {
                    $this->add($entry, 'owner_tax', LedgerDirection::Credit,
                        round(($total - $tax - $commission) * Settings::float('owner_tax_percent') / 100, 2));
                }
            }

            $buyerCommission = (float) $installment->buyer_commission;
            if ($buyerCommission > 0) {
                $entry = $common + ['owner_id' => null];
                $parent = $this->add($entry + [
                    'is_settled' => true,
                    'settled_at' => $installment->collected_at ?? now(),
                    'receipt_voucher_id' => $installment->receipt_voucher_id,
                ], 'commission2', LedgerDirection::Debit, $buyerCommission);
                $this->addIncomeTax($entry, $buyerCommission, $parent);
            }
        });
    }

    /** Expenses paid for a unit are charged to its owners by expenses share (legacy collectStatement). */
    public function recordExpense(PaymentVoucher $voucher, Unit $unit): void
    {
        DB::transaction(function () use ($voucher, $unit) {
            foreach ($unit->owners()->get() as $owner) {
                $expensesShare = (float) $owner->pivot->expenses_percent;
                if ($expensesShare <= 0) {
                    continue;
                }
                $this->add([
                    'owner_id' => $owner->id,
                    'unit_id' => $unit->id,
                    'contract_type' => $unit->current_contract_type,
                    'contract_id' => $unit->current_contract_id,
                    'payment_voucher_id' => $voucher->id,
                    'source' => 'sell',
                    'due_date' => $voucher->issued_at,
                ], 'outgoings', LedgerDirection::Credit, round((float) $voucher->amount * $expensesShare / 100, 2));
            }
        });
    }

    /**
     * Charge the periodic management fee to owners and return the next due date
     * (null for a one-off fee). Legacy buildManCostPeriod.
     */
    public function chargeManagementFee(Unit $unit): ?CarbonImmutable
    {
        $cycle = $unit->management_fee_cycle;
        $dueDate = CarbonImmutable::parse($unit->management_fee_next_date ?? $unit->management_fee_start_date);

        DB::transaction(function () use ($unit, $dueDate) {
            foreach ($unit->owners()->get() as $owner) {
                $expensesShare = (float) $owner->pivot->expenses_percent;
                if ($expensesShare <= 0) {
                    continue;
                }
                $entry = ['owner_id' => $owner->id, 'unit_id' => $unit->id, 'source' => 'build', 'due_date' => $dueDate];
                $amount = round((float) $unit->management_fee * $expensesShare / 100, 2);
                $parent = $this->add($entry, 'mancost', LedgerDirection::Credit, $amount);
                $this->addIncomeTax(['owner_id' => null] + $entry, $amount, $parent);
            }
        });

        if ($cycle === null || $cycle === PaymentCycle::Once) {
            $next = null;
        } else {
            $next = $unit->calendar === Calendar::Hijri
                ? Hijri::addMonths($dueDate, $cycle->months())
                : $dueDate->addMonthsNoOverflow($cycle->months());
        }

        $unit->forceFill(['management_fee_next_date' => $next])->save();

        return $next;
    }

    /** Mark all ledger entries of an installment as settled (legacy finishStatByPaymentID). */
    public function settleForInstallment(Installment $installment): int
    {
        return $installment->ownerTransactions()->where('is_settled', false)
            ->update(['is_settled' => true, 'settled_at' => now()]);
    }

    /** Office income tax on its own revenue (legacy comp_tax), when enabled. */
    private function addIncomeTax(array $entry, float $base, OwnerTransaction $parent): void
    {
        if (! Settings::enabled('income_tax_enabled')) {
            return;
        }
        $this->add(array_merge($entry, ['is_settled' => false, 'settled_at' => null, 'receipt_voucher_id' => null, 'parent_id' => $parent->id]),
            'comp_tax', LedgerDirection::Credit, round($base * Settings::float('income_tax_percent') / 100, 2));
    }

    private function add(array $attributes, string $type, LedgerDirection $direction, float $amount): OwnerTransaction
    {
        return OwnerTransaction::create($attributes + [
            'transaction_type_id' => $this->typeId($type),
            'direction' => $direction,
            'amount' => $amount,
        ]);
    }

    private function typeId(string $code): int
    {
        return $this->typeIds[$code] ??= TransactionType::where('code', $code)->valueOrFail('id');
    }
}
