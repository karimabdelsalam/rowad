<?php

namespace App\Services\Finance;

use App\Enums\InstallmentType;
use App\Enums\LedgerDirection;
use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Unit;
use App\Services\DocumentNumberGenerator;
use App\Services\Installments\InstallmentCollector;
use App\Services\Ledger\OwnerLedger;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/** Port of legacy statementsin / statementsout add, edit and delete flows. */
class VoucherService
{
    public const CHARGE_NONE = null;
    public const CHARGE_OWNERS = 'owners';
    public const CHARGE_CONTRACT = 'contract';

    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly InstallmentCollector $collector,
        private readonly OwnerLedger $ledger,
    ) {
    }

    /**
     * @param  list<int>  $installmentIds  receivables paid by this receipt (legacy paymentids)
     * @param  list<int>  $ownerTransactionIds  income-type owner entries (legacy transids)
     */
    public function createReceipt(array $attributes, array $installmentIds = [], array $ownerTransactionIds = []): ReceiptVoucher
    {
        $installments = Installment::whereKey($installmentIds)->get();
        if ($linked = $installments->firstWhere('receipt_voucher_id', '!=', null)) {
            throw new BusinessRuleException(__('القسط رقم :id مرتبط بسند قبض آخر', ['id' => $linked->id]));
        }
        $entries = OwnerTransaction::with('type')->whereKey($ownerTransactionIds)->get();
        if ($linked = $entries->firstWhere('receipt_voucher_id', '!=', null)) {
            throw new BusinessRuleException(__('الحركة رقم :id مرتبطة بسند قبض آخر', ['id' => $linked->id]));
        }
        if ($wrong = $entries->first(fn ($e) => ! $e->type->is_income)) {
            throw new BusinessRuleException(__('الحركة رقم :id لا تقبل إنشاء سند قبض', ['id' => $wrong->id]));
        }

        return DB::transaction(function () use ($attributes, $installments, $entries) {
            $voucher = ReceiptVoucher::create($attributes + [
                'number' => $this->numbers->next('receipt_voucher'),
                'created_by' => auth()->id(),
            ]);

            foreach ($installments as $installment) {
                $installment->is_collected
                    ? $installment->forceFill(['receipt_voucher_id' => $voucher->id])->save()
                    : $this->collector->collect($installment, $voucher);
            }
            foreach ($entries as $entry) {
                $entry->forceFill(['receipt_voucher_id' => $voucher->id, 'is_settled' => true, 'settled_at' => now()])->save();
            }

            $this->syncBank($voucher, 'deposit');

            return $voucher;
        });
    }

    /**
     * @param  string|null  $chargeTo  legacy buildexpenses + expensestype: owners or the unit's current contract
     * @param  list<int>  $ownerTransactionIds  owner entries settled by this payout (legacy transids)
     */
    public function createPayment(array $attributes, ?string $chargeTo = null, array $ownerTransactionIds = [], bool $withVat = false): PaymentVoucher
    {
        $entries = OwnerTransaction::whereKey($ownerTransactionIds)->get();
        if ($linked = $entries->firstWhere('payment_voucher_id', '!=', null)) {
            throw new BusinessRuleException(__('الحركة رقم :id مرتبطة بسند صرف آخر', ['id' => $linked->id]));
        }
        if ($chargeTo !== null && empty($attributes['unit_id'])) {
            throw new BusinessRuleException(__('اختر الوحدة لتحميل المصروف عليها'));
        }

        $vat = $withVat ? round((float) $attributes['amount'] * Settings::float('vat_percent') / 100, 2) : 0.0;
        $attributes['vat_amount'] = $vat;
        $attributes['amount'] = round((float) $attributes['amount'] + $vat, 2);

        return DB::transaction(function () use ($attributes, $chargeTo, $entries, $vat) {
            $voucher = PaymentVoucher::create($attributes + [
                'number' => $this->numbers->next('payment_voucher'),
                'created_by' => auth()->id(),
            ]);

            foreach ($entries as $entry) {
                $entry->forceFill(['payment_voucher_id' => $voucher->id, 'is_settled' => true, 'settled_at' => now()])->save();
            }

            if ($chargeTo === self::CHARGE_OWNERS) {
                $this->ledger->recordExpense($voucher, Unit::findOrFail($voucher->unit_id));
            } elseif ($chargeTo === self::CHARGE_CONTRACT) {
                $this->chargeContract($voucher, $vat);
            }

            $this->syncBank($voucher, 'withdrawal');

            return $voucher;
        });
    }

    /** Re-point or drop the bank movement after an edit (legacy checkBankTransaction). */
    public function syncBank(ReceiptVoucher|PaymentVoucher $voucher, ?string $type = null): void
    {
        $type ??= $voucher instanceof ReceiptVoucher ? 'deposit' : 'withdrawal';
        $sign = $type === 'deposit' ? 1 : -1;
        $existing = $voucher->bankTransaction()->first();

        if ($existing !== null) {
            BankAccount::whereKey($existing->bank_account_id)->decrement('balance', $sign * (float) $existing->amount);
            $existing->delete();
        }
        if ($voucher->bank_account_id) {
            $voucher->bankTransaction()->create([
                'bank_account_id' => $voucher->bank_account_id,
                'type' => $type,
                'amount' => $voucher->amount,
                'description' => $voucher->reason,
                'occurred_at' => $voucher->issued_at,
            ]);
            BankAccount::whereKey($voucher->bank_account_id)->increment('balance', $sign * (float) $voucher->amount);
        }
    }

    /** Delete a voucher and undo its links and bank movement. Collected installments stay collected. */
    public function delete(ReceiptVoucher|PaymentVoucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            $column = $voucher instanceof ReceiptVoucher ? 'receipt_voucher_id' : 'payment_voucher_id';
            if ($voucher instanceof PaymentVoucher) {
                // Expense charges created by this voucher go away; payouts it settled are reopened below.
                OwnerTransaction::where('payment_voucher_id', $voucher->id)
                    ->whereHas('type', fn ($q) => $q->where('code', 'outgoings'))->delete();
                if (Installment::where('payment_voucher_id', $voucher->id)->where('is_collected', true)->exists()) {
                    throw new BusinessRuleException(__('لا يمكن حذف السند لأن المصروف المحمل على العقد تم تحصيله'));
                }
                Installment::where('payment_voucher_id', $voucher->id)->where('type', InstallmentType::Expenses)->delete();
            }
            OwnerTransaction::where($column, $voucher->id)->update([$column => null, 'is_settled' => false, 'settled_at' => null]);
            Installment::where($column, $voucher->id)->update([$column => null]);
            $voucher->forceFill(['bank_account_id' => null]);
            $this->syncBank($voucher);
            $voucher->delete();
        });
    }

    /** Legacy expensesOnContract: bill the renter/buyer of the unit for an expense. */
    private function chargeContract(PaymentVoucher $voucher, float $vat): void
    {
        $unit = Unit::findOrFail($voucher->unit_id);
        $contract = $unit->currentContract;
        if ($contract === null) {
            throw new BusinessRuleException(__('لا يوجد عقد ساري على الوحدة لتحميل المصروف عليه'));
        }

        $contract->installments()->create([
            'unit_id' => $unit->id,
            'type' => InstallmentType::Expenses,
            'calendar' => $voucher->calendar,
            'due_date' => $voucher->issued_at,
            'amount' => $voucher->amount,
            'vat_amount' => $vat,
            'vat_percent' => $vat > 0 ? Settings::float('vat_percent') : 0,
            'payment_voucher_id' => $voucher->id,
            'reason' => $voucher->reason,
        ]);
    }
}
