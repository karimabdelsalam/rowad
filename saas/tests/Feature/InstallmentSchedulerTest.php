<?php

namespace Tests\Feature;

use App\Enums\InstallmentType;
use App\Exceptions\BusinessRuleException;
use App\Services\Installments\InstallmentCollector;
use App\Services\Installments\InstallmentScheduler;
use App\Support\Hijri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Expected values follow legacy payments_model::rentContractSetup / sellContractSetup. */
class InstallmentSchedulerTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private InstallmentScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        $this->scheduler = app(InstallmentScheduler::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_yearly_contract_paid_monthly_with_one_off_commission(): void
    {
        $contract = $this->makeRentContract(['commission' => 500, 'commission_cycle' => 'once', 'vat_amount' => 130.43, 'vat_percent' => 15]);

        $rows = $this->scheduler->schedule($contract);

        $this->assertCount(12, $rows);
        $this->assertSame(InstallmentType::YearRentMonthly, $rows[0]->type);
        $this->assertSame(['1500.00', '500.00'], [$rows[0]->amount, $rows[0]->buyer_commission]);
        $this->assertSame(['1000.00', '0.00'], [$rows[1]->amount, $rows[1]->buyer_commission]);
        $this->assertSame('130.43', $rows[5]->vat_amount);
        $this->assertSame('2026-01-01', $rows[0]->due_date->toDateString());
        $this->assertSame('2026-12-01', $rows[11]->due_date->toDateString());
        $this->assertEquals(12500, $contract->installments()->sum('amount'));
    }

    public function test_quarterly_payments_with_yearly_commission(): void
    {
        $contract = $this->makeRentContract(['period_count' => 2, 'payment_cycle' => 'quarter', 'rent_amount' => 3000,
            'commission' => 1200, 'commission_cycle' => 'year', 'end_date' => '2027-12-31']);

        $rows = $this->scheduler->schedule($contract);

        $this->assertCount(8, $rows);
        $this->assertSame(InstallmentType::YearRentQuarterly, $rows[0]->type);
        $this->assertSame(['4200.00', '3000.00', '3000.00', '3000.00', '4200.00'], $rows->take(5)->pluck('amount')->all());
        $this->assertSame('2027-10-01', $rows[7]->due_date->toDateString());
    }

    public function test_daily_rent_is_billed_as_one_installment_on_the_last_day(): void
    {
        $contract = $this->makeRentContract(['period_unit' => 'day', 'period_count' => 10, 'payment_cycle' => 'day',
            'rent_amount' => 100, 'commission' => 10, 'commission_cycle' => 'day', 'end_date' => '2026-01-10']);

        $rows = $this->scheduler->schedule($contract);

        $this->assertCount(1, $rows);
        $this->assertSame(InstallmentType::DayRent, $rows[0]->type);
        $this->assertSame(['1100.00', '100.00', 15], [$rows[0]->amount, $rows[0]->buyer_commission, $rows[0]->reminder_stage]);
        $this->assertSame('2026-01-10', $rows[0]->due_date->toDateString());
    }

    public function test_month_end_start_dates_do_not_overflow(): void
    {
        $contract = $this->makeRentContract(['start_date' => '2026-01-31', 'period_unit' => 'month', 'period_count' => 3]);

        $rows = $this->scheduler->schedule($contract);

        $this->assertSame(InstallmentType::MonthRent, $rows[0]->type);
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $rows->map(fn ($r) => $r->due_date->toDateString())->all());
    }

    public function test_hijri_contracts_step_by_hijri_months(): void
    {
        $contract = $this->makeRentContract(['calendar' => 'hijri', 'start_date' => '2026-10-15', 'period_unit' => 'month', 'period_count' => 3]);

        $rows = $this->scheduler->schedule($contract);

        $this->assertSame(['1448/05/04', '1448/06/04', '1448/07/04'], $rows->map(fn ($r) => Hijri::toString($r->due_date))->all());
    }

    public function test_sale_paid_once_with_split_percentage_commission(): void
    {
        $contract = $this->makeSaleContract(['total_amount' => 500000, 'payment_type' => 'once',
            'payment_plan' => ['commission' => 2.5, 'commission_type' => 'percent', 'commission_payer' => 'split']]);

        $row = $this->scheduler->schedule($contract)->sole();

        $this->assertSame(InstallmentType::InstantSale, $row->type);
        $this->assertSame(['506250.00', '6250.00', '6250.00', 'percent'], [$row->amount, $row->seller_commission, $row->buyer_commission, $row->commission_type]);
        $this->assertSame('2026-03-01', $row->due_date->toDateString());
    }

    public function test_sale_in_parts_uses_due_date_for_deferred_cheques(): void
    {
        $contract = $this->makeSaleContract(['total_amount' => 300000, 'payment_type' => 'parts', 'payment_plan' => [
            'commission_payer' => 'buyer',
            'parts' => [
                ['amount' => 100000, 'commission' => 3000, 'received_date' => '2026-03-01', 'deferred' => false],
                ['amount' => 200000, 'commission' => 0, 'received_date' => '2026-03-01', 'due_date' => '2026-09-01', 'deferred' => true],
            ],
        ]]);

        $rows = $this->scheduler->schedule($contract);

        $this->assertSame(['103000.00', '200000.00'], $rows->pluck('amount')->all());
        $this->assertSame('2026-09-01', $rows[1]->due_date->toDateString());
    }

    public function test_sale_parts_must_add_up_to_the_contract_total(): void
    {
        $contract = $this->makeSaleContract(['total_amount' => 300000, 'payment_type' => 'parts', 'payment_plan' => [
            'parts' => [['amount' => 100000, 'received_date' => '2026-03-01']],
        ]]);

        $this->expectException(BusinessRuleException::class);
        $this->scheduler->schedule($contract);
    }

    public function test_sale_installments_exclude_the_down_payment_and_start_one_cycle_later(): void
    {
        $contract = $this->makeSaleContract(['total_amount' => 120000, 'payment_type' => 'installment', 'payment_plan' => [
            'first_payment' => 20000, 'count' => 10, 'cycle' => 'month', 'start_date' => '2026-01-01',
        ]]);

        $rows = $this->scheduler->schedule($contract);

        $this->assertCount(10, $rows);
        $this->assertSame('10000.00', $rows[0]->amount);
        $this->assertSame('2026-02-01', $rows[0]->due_date->toDateString());
        $this->assertSame('2026-11-01', $rows[9]->due_date->toDateString());
    }

    public function test_contract_with_collected_money_cannot_be_rescheduled(): void
    {
        $contract = $this->makeRentContract();
        $first = $this->scheduler->schedule($contract)->first();
        app(InstallmentCollector::class)->collect($first);

        $this->expectExceptionMessage('لا يمكن التعديل على العقد في وجود مبالغ مالية مرحلة');
        $this->scheduler->schedule($contract);
    }

    public function test_rescheduling_keeps_expense_installments(): void
    {
        $contract = $this->makeRentContract();
        $this->scheduler->schedule($contract);
        $contract->installments()->create(['unit_id' => $contract->unit_id, 'type' => 'expenses', 'due_date' => '2026-02-01', 'amount' => 75]);

        $this->scheduler->schedule($contract->fresh());

        $this->assertSame(13, $contract->installments()->count());
    }
}
