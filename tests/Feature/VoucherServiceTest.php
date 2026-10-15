<?php

namespace Tests\Feature;

use App\Enums\InstallmentType;
use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Services\Contracts\ContractService;
use App\Services\Finance\VoucherService;
use App\Services\Installments\InstallmentCollector;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class VoucherServiceTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private VoucherService $vouchers;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $this->initializeTenancy();
        $this->vouchers = app(VoucherService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function rented(array $owners = [[100, 100]]): \App\Models\RentContract
    {
        $unit = $this->makeUnit($owners);

        return app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter', 'rent_amount' => 5000,
        ], [$this->makeClient()->id => 100]);
    }

    private function bank(): BankAccount
    {
        return BankAccount::create(['holder_name' => 'حساب المكتب', 'balance' => 1000]);
    }

    public function test_receipt_collects_linked_installments_and_deposits_to_the_bank(): void
    {
        $contract = $this->rented();
        $bank = $this->bank();
        [$first, $second] = $contract->installments()->orderBy('due_date')->take(2)->get()->all();

        $voucher = $this->vouchers->createReceipt([
            'issued_at' => '2026-01-10', 'amount' => 10000, 'payment_method' => 'transfer', 'bank_account_id' => $bank->id,
            'party_type' => 'client', 'party_id' => $contract->clients()->first()->id, 'reason' => 'إيجار ربعين',
        ], [$first->id, $second->id]);

        $this->assertSame('RV-2026-00001', $voucher->number);
        $this->assertTrue($first->fresh()->is_collected);
        $this->assertSame($voucher->id, $second->fresh()->receipt_voucher_id);
        $this->assertSame(2, OwnerTransaction::whereNotNull('installment_id')->whereHas('type', fn ($q) => $q->where('code', 'payment'))->count());
        $this->assertSame('11000.00', $bank->fresh()->balance);
        $this->assertSame('deposit', $voucher->bankTransaction->type);
    }

    public function test_an_installment_cannot_be_linked_to_two_receipts(): void
    {
        $installment = $this->rented()->installments()->first();
        $this->vouchers->createReceipt(['issued_at' => '2026-01-10', 'amount' => 5000], [$installment->id]);

        $this->expectException(BusinessRuleException::class);
        $this->vouchers->createReceipt(['issued_at' => '2026-01-11', 'amount' => 5000], [$installment->id]);
    }

    public function test_payment_voucher_with_vat_charged_to_owners(): void
    {
        Settings::set('vat_percent', 15);
        $contract = $this->rented([[50, 70], [50, 30]]);
        $bank = $this->bank();

        $voucher = $this->vouchers->createPayment(
            ['issued_at' => '2026-02-01', 'amount' => 1000, 'unit_id' => $contract->unit_id, 'bank_account_id' => $bank->id, 'reason' => 'صيانة مكيفات'],
            VoucherService::CHARGE_OWNERS,
            withVat: true,
        );

        $this->assertSame(['1150.00', '150.00'], [$voucher->amount, $voucher->vat_amount]);
        $this->assertEqualsCanonicalizing(['805.00', '345.00'], OwnerTransaction::where('payment_voucher_id', $voucher->id)->pluck('amount')->all());
        $this->assertSame('-150.00', $bank->fresh()->balance);
    }

    public function test_payment_voucher_charged_to_the_renter_creates_an_expense_installment(): void
    {
        $contract = $this->rented();

        $voucher = $this->vouchers->createPayment(['issued_at' => '2026-02-01', 'amount' => 300, 'unit_id' => $contract->unit_id, 'reason' => 'فاتورة كهرباء'],
            VoucherService::CHARGE_CONTRACT);

        $expense = Installment::where('type', InstallmentType::Expenses)->sole();
        $this->assertTrue($expense->contract->is($contract));
        $this->assertSame(['300.00', $voucher->id], [$expense->amount, $expense->payment_voucher_id]);
    }

    public function test_owner_payout_settles_entries_and_deleting_the_voucher_reopens_them(): void
    {
        $contract = $this->rented();
        app(InstallmentCollector::class)->collect($contract->installments()->first());
        $share = OwnerTransaction::whereHas('type', fn ($q) => $q->where('code', 'payment'))->sole();
        $bank = $this->bank();

        $voucher = $this->vouchers->createPayment(['issued_at' => '2026-02-01', 'amount' => 5000, 'bank_account_id' => $bank->id,
            'party_type' => 'owner', 'party_id' => $share->owner_id, 'reason' => 'تحويل مستحقات'], ownerTransactionIds: [$share->id]);

        $this->assertTrue($share->fresh()->is_settled);
        $this->assertSame('-4000.00', $bank->fresh()->balance);

        $this->vouchers->delete($voucher);

        $this->assertFalse($share->fresh()->is_settled);
        $this->assertNull($share->fresh()->payment_voucher_id);
        $this->assertSame('1000.00', $bank->fresh()->balance);
        $this->assertSoftDeleted($voucher);
    }

    public function test_changing_the_bank_account_moves_the_balance(): void
    {
        [$a, $b] = [$this->bank(), $this->bank()];
        $voucher = $this->vouchers->createReceipt(['issued_at' => '2026-01-10', 'amount' => 700, 'bank_account_id' => $a->id]);

        $voucher->update(['bank_account_id' => $b->id]);
        $this->vouchers->syncBank($voucher);

        $this->assertSame(['1000.00', '1700.00'], [$a->fresh()->balance, $b->fresh()->balance]);
        $this->assertSame(1, \App\Models\BankTransaction::count());
    }
}
