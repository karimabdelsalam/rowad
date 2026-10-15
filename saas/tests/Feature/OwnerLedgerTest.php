<?php

namespace Tests\Feature;

use App\Enums\LedgerDirection;
use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Services\Installments\InstallmentCollector;
use App\Services\Ledger\OwnerLedger;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Expected values follow legacy transactions::collectPayment / buildManCostPeriod / collectStatement. */
class OwnerLedgerTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function installment(array $owners, array $attributes = []): Installment
    {
        $contract = $this->makeRentContract([], $this->makeUnit($owners));

        return $contract->installments()->create($attributes + [
            'unit_id' => $contract->unit_id, 'type' => 'year_rent_month', 'due_date' => '2026-05-01',
            'amount' => 1150, 'buyer_commission' => 50, 'seller_commission' => 100, 'vat_amount' => 150, 'vat_percent' => 15,
        ]);
    }

    /** @return array<string, string> "owner:code:direction" => amount */
    private function entries(): array
    {
        return OwnerTransaction::with(['type', 'owner'])->orderBy('id')->get()
            ->mapWithKeys(fn ($t) => [($t->owner?->family_name ?? 'office').":{$t->type->code}:{$t->direction->value}" => $t->amount])
            ->all();
    }

    /** @return list<string> "owner:code:direction=amount" in insertion order */
    private function entryList(): array
    {
        return OwnerTransaction::with(['type', 'owner'])->orderBy('id')->get()
            ->map(fn ($t) => ($t->owner?->family_name ?? 'office').":{$t->type->code}:{$t->direction->value}={$t->amount}")
            ->all();
    }

    public function test_collection_splits_shares_vat_commission_and_taxes_between_owners(): void
    {
        Settings::set('income_tax_enabled', true);
        Settings::set('income_tax_percent', 10);
        Settings::set('owner_tax_enabled', true);
        Settings::set('owner_tax_percent', 5);

        $installment = $this->installment([[60, 60], [40, 40]]);
        app(InstallmentCollector::class)->collect($installment);

        $this->assertSame([
            '1:payment:debit' => '660.00',
            '1:tax:credit' => '90.00',
            '1:commission:credit' => '60.00',
            '1:comp_tax:credit' => '6.00',
            '1:owner_tax:credit' => '25.50',
            '2:payment:debit' => '440.00',
            '2:tax:credit' => '60.00',
            '2:commission:credit' => '40.00',
            '2:comp_tax:credit' => '4.00',
            '2:owner_tax:credit' => '17.00',
            'office:commission2:debit' => '50.00',
            'office:comp_tax:credit' => '5.00',
        ], $this->entries());

        $owner = $installment->unit->owners()->where('family_name', '1')->first();
        $this->assertSame(478.5, $owner->outstandingBalance());
        $this->assertTrue(OwnerTransaction::whereNull('owner_id')->whereHas('type', fn ($q) => $q->where('code', 'commission2'))->value('is_settled'));
    }

    public function test_collection_without_optional_taxes(): void
    {
        $installment = $this->installment([[100, 0]], ['vat_amount' => 0, 'seller_commission' => 100]);
        app(InstallmentCollector::class)->collect($installment);

        // No expenses share => no commission deducted from owner (legacy !empty(outgoings) check).
        $this->assertSame(['1:payment:debit' => '1100.00', 'office:commission2:debit' => '50.00'], $this->entries());
    }

    public function test_collecting_twice_does_not_duplicate_ledger_entries(): void
    {
        $installment = $this->installment([[100, 100]]);
        $collector = app(InstallmentCollector::class);
        $collector->collect($installment);
        $collector->collect($installment->fresh());
        app(OwnerLedger::class)->recordCollection($installment->fresh());

        $this->assertSame(1, OwnerTransaction::whereHas('type', fn ($q) => $q->where('code', 'payment'))->count());
    }

    public function test_partial_payments_accumulate_and_close_the_installment_when_complete(): void
    {
        $installment = $this->installment([[100, 100]], ['amount' => 1000, 'buyer_commission' => 0, 'seller_commission' => 0, 'vat_amount' => 0]);
        $collector = app(InstallmentCollector::class);

        $collector->collectPartial($installment, 400);
        $this->assertFalse($installment->fresh()->is_collected);
        $this->assertSame('400.00', $installment->fresh()->paid_amount);

        $collector->collectPartial($installment->fresh(), 600);
        $this->assertTrue($installment->fresh()->is_collected);
        $this->assertSame(2, $installment->payments()->count());
        $this->assertSame(['1:payment:debit' => '1000.00'], $this->entries());
    }

    public function test_partial_payment_cannot_exceed_the_amount_due(): void
    {
        $installment = $this->installment([[100, 100]], ['amount' => 1000]);
        app(InstallmentCollector::class)->collectPartial($installment, 700);

        $this->expectException(BusinessRuleException::class);
        app(InstallmentCollector::class)->collectPartial($installment->fresh(), 301);
    }

    public function test_partial_payments_can_be_reset(): void
    {
        $installment = $this->installment([[100, 100]], ['amount' => 1000]);
        $collector = app(InstallmentCollector::class);
        $collector->collectPartial($installment, 700);

        $collector->resetPartial($installment->fresh());

        $this->assertSame('0.00', $installment->fresh()->paid_amount);
        $this->assertSame(0, $installment->payments()->count());
    }

    public function test_management_fee_is_charged_by_expenses_share_and_advances_next_date(): void
    {
        Settings::set('income_tax_enabled', true);
        Settings::set('income_tax_percent', 10);
        $unit = $this->makeUnit([[50, 60], [50, 40]], ['management_fee' => 1000, 'management_fee_cycle' => 'month', 'management_fee_start_date' => '2026-01-15']);

        $next = app(OwnerLedger::class)->chargeManagementFee($unit);

        $this->assertSame('2026-02-15', $next->toDateString());
        $this->assertSame('2026-02-15', $unit->fresh()->management_fee_next_date->toDateString());
        $this->assertSame([
            '1:mancost:credit=600.00',
            'office:comp_tax:credit=60.00',
            '2:mancost:credit=400.00',
            'office:comp_tax:credit=40.00',
        ], $this->entryList());
    }

    public function test_one_off_management_fee_has_no_next_date(): void
    {
        $unit = $this->makeUnit([[100, 100]], ['management_fee' => 500, 'management_fee_cycle' => 'once', 'management_fee_start_date' => '2026-01-15']);

        $this->assertNull(app(OwnerLedger::class)->chargeManagementFee($unit));
    }

    public function test_unit_expenses_are_charged_to_owners_by_expenses_share(): void
    {
        $unit = $this->makeUnit([[70, 30], [30, 70]]);
        $voucher = PaymentVoucher::create(['issued_at' => '2026-04-10', 'amount' => 2000, 'unit_id' => $unit->id]);

        app(OwnerLedger::class)->recordExpense($voucher, $unit);

        $this->assertSame(['1:outgoings:credit' => '600.00', '2:outgoings:credit' => '1400.00'], $this->entries());
        $this->assertSame(LedgerDirection::Credit, OwnerTransaction::first()->direction);
    }
}
