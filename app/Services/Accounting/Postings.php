<?php

namespace App\Services\Accounting;

use App\Enums\InstallmentType;
use App\Models\BankAccount;
use App\Models\EmployeeAdvance;
use App\Models\EndOfServiceSettlement;
use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\PayrollItem;
use App\Models\ReceiptVoucher;
use Illuminate\Database\Eloquent\Model;

/**
 * What each document posts (cash basis: entries follow the money and the owner ledger).
 *
 * - Receipt: debit the cash box / bank. Credit output VAT, the office's commission paid by the tenant or
 *   buyer, expenses re-billed to the tenant, and the rest to the owners' trust account. A receipt with
 *   nothing to do with owners credits its chosen account (default: other income).
 * - Payment: credit the cash box / bank. Debit expenses charged to owners (trust account), to the tenant
 *   (receivable), payouts to an owner (trust account), employee advances, salaries (with advances
 *   recovered and GOSI accrued), input VAT, and the rest to its chosen account (default: general expenses).
 * - Owner ledger: commission and management fees move money from the trust account to the office's
 *   revenue; taxes on the owner go to taxes payable; the office's income tax is an expense.
 * - Bank account: its opening balance against capital.
 *
 * Each posting returns [date, description, lines]; a line is [system key | account id, debit, credit, memo?, owner id?].
 */
final class Postings
{
    /** @return array{0: mixed, 1: string, 2: list<array>}|null */
    public static function for(Model $source): ?array
    {
        return match (true) {
            $source instanceof ReceiptVoucher => self::receipt($source),
            $source instanceof PaymentVoucher => self::payment($source),
            $source instanceof OwnerTransaction => self::ownerEntry($source),
            $source instanceof BankAccount => self::openingBalance($source),
            default => null,
        };
    }

    private static function receipt(ReceiptVoucher $voucher): ?array
    {
        $amount = (float) $voucher->amount;
        if ($amount <= 0) {
            return null;
        }

        $vat = 0.0;
        $commission = 0.0;
        $rebilled = 0.0;
        $commissionKey = 'rent_commission';
        $forOwners = $voucher->party_type === 'owner'
            || InstallmentPayment::query()->where('receipt_voucher_id', $voucher->id)->exists()
            || OwnerTransaction::query()->where('receipt_voucher_id', $voucher->id)->exists();

        $installments = Installment::query()->where('receipt_voucher_id', $voucher->id)->get();
        foreach ($installments as $installment) {
            if ($installment->type === InstallmentType::Expenses) {
                $rebilled += (float) $installment->amount - (float) $installment->vat_amount;
                $vat += (float) $installment->vat_amount;

                continue;
            }
            $forOwners = true;
            $vat += (float) $installment->vat_amount;
            $commission += (float) $installment->buyer_commission;
            if (! $installment->type->isRent()) {
                $commissionKey = 'sale_commission';
            }
        }
        if ($installments->isEmpty()) {
            $vat = (float) $voucher->vat_amount;
        }

        $credits = array_filter(['vat_output' => $vat, $commissionKey => $commission, 'tenants' => $rebilled], fn ($v) => $v > 0);
        $rest = $amount - array_sum($credits);
        if ($rest < -0.004) {
            // the receipt is smaller than what it is linked to (a partial amount): share it out proportionally
            $scale = $amount / array_sum($credits);
            $credits = array_map(fn ($v) => round($v * $scale, 2), $credits);
            $rest = $amount - array_sum($credits);
        }
        $restAccount = $forOwners ? 'owners' : ($voucher->account_id ?? 'other_income');

        $lines = [[Accounts::money($voucher->bank_account_id), $amount, 0]];
        foreach ($credits as $key => $value) {
            $lines[] = [$key, 0, $value];
        }
        $lines[] = [$restAccount, 0, $rest, null, $voucher->party_type === 'owner' ? $voucher->party_id : null];

        return [$voucher->issued_at, self::describe(__('سند قبض :number', ['number' => $voucher->number]), $voucher->party_name, $voucher->reason), $lines];
    }

    private static function payment(PaymentVoucher $voucher): ?array
    {
        $amount = (float) $voucher->amount;
        if ($amount <= 0) {
            return null;
        }
        $vat = (float) $voucher->vat_amount;
        $lines = [[Accounts::money($voucher->bank_account_id), 0, $amount]];
        $left = $amount;
        $debit = function (string|int $account, float $value, ?string $memo = null, ?int $owner = null) use (&$lines, &$left) {
            $value = round(min($value, $left), 2);
            if ($value > 0) {
                $lines[] = [$account, $value, 0, $memo, $owner];
                $left = round($left - $value, 2);
            }
        };

        $rebilled = Installment::query()->where('payment_voucher_id', $voucher->id)->where('type', InstallmentType::Expenses)->first();
        $chargedToOwners = (float) OwnerTransaction::query()->where('payment_voucher_id', $voucher->id)
            ->whereHas('type', fn ($q) => $q->where('code', 'outgoings'))->sum('amount');
        $payroll = PayrollItem::query()->where('payment_voucher_id', $voucher->id)->first();

        if ($rebilled !== null) {
            $debit('vat_input', (float) $rebilled->vat_amount);
            $debit('tenants', $left, __('مصروف محمّل على المستأجر'));
        } elseif ($chargedToOwners > 0) {
            $debit('owners', $chargedToOwners, __('مصروف محمّل على الملاك'));
            $debit('vat_input', $vat);
        } elseif ($voucher->party_type === 'owner') {
            $debit('owners', $left, __('تحويل مستحقات للمالك'), $voucher->party_id);
        } elseif (EmployeeAdvance::query()->where('payment_voucher_id', $voucher->id)->exists()) {
            $debit('employee_advances', $left);
        } elseif ($payroll !== null) {
            // salary expense = what was paid + advances recovered + GOSI (both shares), which become liabilities / cleared assets
            $advances = (float) $payroll->advances;
            $gosi = (float) $payroll->gosi_employee + (float) $payroll->gosi_employer;
            $lines[] = ['salaries', round($left + $advances + $gosi, 2), 0];
            if ($advances > 0) {
                $lines[] = ['employee_advances', 0, $advances];
            }
            if ($gosi > 0) {
                $lines[] = ['gosi_payable', 0, $gosi];
            }
            $left = 0;
        } elseif (EndOfServiceSettlement::query()->where('payment_voucher_id', $voucher->id)->exists()) {
            $debit('salaries', $left, __('مكافأة نهاية الخدمة'));
        } else {
            $debit('vat_input', $vat);
        }
        $debit($voucher->account_id ?? 'general_expenses', $left);

        return [$voucher->issued_at, self::describe(__('سند صرف :number', ['number' => $voucher->number]), $voucher->party_name, $voucher->reason), $lines];
    }

    private static function ownerEntry(OwnerTransaction $entry): ?array
    {
        $amount = (float) $entry->amount;
        $code = $entry->type?->code;
        [$debit, $credit] = match ($code) {
            'commission' => ['owners', $entry->source === 'sell' ? 'sale_commission' : 'rent_commission'],
            'mancost' => ['owners', 'management_fees'],
            'owner_tax' => ['owners', 'taxes_payable'],
            'comp_tax' => ['income_tax', 'taxes_payable'],
            default => [null, null],
        };
        if ($debit === null || $amount <= 0) {
            return null;
        }
        $owner = $debit === 'owners' ? $entry->owner_id : null;

        return [$entry->due_date ?? $entry->created_at, (string) $entry->type->title.($entry->unit?->title ? ' — '.$entry->unit->title : ''),
            [[$debit, $amount, 0, null, $owner], [$credit, 0, $amount]]];
    }

    private static function openingBalance(BankAccount $bank): ?array
    {
        $amount = (float) $bank->opening_balance;
        if ($amount == 0.0 || $bank->trashed()) {
            return null;
        }
        $account = Accounts::forBank($bank)->id;

        return [$bank->created_at ?? now(), __('رصيد افتتاحي — :name', ['name' => $bank->holder_name]), $amount > 0
            ? [[$account, $amount, 0], ['capital', 0, $amount]]
            : [['capital', -$amount, 0], [$account, 0, -$amount]]];
    }

    private static function describe(string $title, ?string $party, ?string $reason): string
    {
        return mb_substr(implode(' — ', array_filter([$title, $party, $reason])), 0, 250);
    }
}
