<?php

namespace App\Services\Installments;

use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Models\ReceiptVoucher;
use App\Services\Ledger\OwnerLedger;
use Illuminate\Support\Facades\DB;

/** Collecting receivables. Port of legacy pays_pending process / partpay / reset_partpay. */
class InstallmentCollector
{
    public function __construct(private readonly OwnerLedger $ledger)
    {
    }

    /** Mark as fully collected and split it to owners (legacy processExec). */
    public function collect(Installment $installment, ?ReceiptVoucher $voucher = null): Installment
    {
        if ($installment->is_collected) {
            return $installment;
        }

        return DB::transaction(function () use ($installment, $voucher) {
            $installment->forceFill([
                'is_collected' => true,
                'collected_at' => now(),
                'receipt_voucher_id' => $voucher?->id ?? $installment->receipt_voucher_id,
            ])->save();

            $this->ledger->recordCollection($installment);

            return $installment;
        });
    }

    /** Record a partial payment; optionally close the installment (legacy partpay). */
    public function collectPartial(Installment $installment, float $amount, ?ReceiptVoucher $voucher = null, bool $close = false, bool $settleOwners = false): Installment
    {
        if ($amount <= 0) {
            throw new BusinessRuleException(__('المبلغ المدفوع غير صالح'));
        }
        if (round((float) $installment->paid_amount + $amount, 2) > round((float) $installment->amount, 2)) {
            throw new BusinessRuleException(__('المبلغ المدفوع تعدى إجمالي المبلغ المستحق'));
        }

        return DB::transaction(function () use ($installment, $amount, $voucher, $close, $settleOwners) {
            $installment->payments()->create([
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'receipt_voucher_id' => $voucher?->id,
                'created_by' => auth()->id(),
            ]);
            $installment->increment('paid_amount', $amount);

            if ($close || round((float) $installment->paid_amount, 2) >= round((float) $installment->amount, 2)) {
                $this->collect($installment->refresh(), $voucher);
            }
            if ($settleOwners) {
                $this->ledger->settleForInstallment($installment);
            }

            return $installment->refresh();
        });
    }

    /**
     * Back to pending (legacy pays_processed pending): drops the ledger entries the collection made.
     * Refused once an owner's share was settled or paid out — that money has already left the office.
     */
    public function uncollect(Installment $installment): Installment
    {
        if (! $installment->is_collected) {
            return $installment;
        }
        $paidOut = $installment->ownerTransactions()->whereNotNull('owner_id')
            ->where(fn ($query) => $query->where('is_settled', true)->orWhereNotNull('payment_voucher_id'))->exists();
        if ($paidOut) {
            throw new BusinessRuleException(__('لا يمكن إلغاء ترحيل القسط لوجود مستحقات ملاك مرتبطة به وتم ترحيلها'));
        }

        return DB::transaction(function () use ($installment) {
            $installment->ownerTransactions()->whereNotNull('parent_id')->delete();
            $installment->ownerTransactions()->delete();
            $installment->forceFill(['is_collected' => false, 'collected_at' => null])->save();

            return $installment;
        });
    }

    /** Undo partial payments of an uncollected installment (legacy reset_partpay). */
    public function resetPartial(Installment $installment): Installment
    {
        if ($installment->is_collected) {
            throw new BusinessRuleException(__('لا يمكن إعادة تعيين دفعة تم تحصيلها'));
        }
        DB::transaction(function () use ($installment) {
            $installment->payments()->delete();
            $installment->forceFill(['paid_amount' => 0])->save();
        });

        return $installment;
    }
}
