<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Services\Contracts\ContractService;
use App\Services\Installments\InstallmentCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ContractServiceTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private ContractService $contracts;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $this->initializeTenancy();
        $this->contracts = app(ContractService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function rentAttributes(int $unitId, array $overrides = []): array
    {
        return $overrides + [
            'unit_id' => $unitId, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1,
            'payment_cycle' => 'quarter', 'rent_amount' => 6000,
        ];
    }

    public function test_creating_a_rent_contract_numbers_it_schedules_it_and_occupies_the_unit(): void
    {
        $unit = $this->makeUnit();
        [$a, $b] = [$this->makeClient(), $this->makeClient()];

        $contract = $this->contracts->createRent($this->rentAttributes($unit->id), [$a->id => 50, $b->id => 50]);

        $this->assertSame('RC-2026-00001', $contract->number);
        $this->assertSame(4, $contract->installments()->count());
        $this->assertEquals([50, 50], $contract->clients->pluck('pivot.share_percent')->map(fn ($v) => (float) $v)->all());
        $this->assertSame('2026-01-01', $contract->next_payment_date->toDateString());
        $this->assertSame(UnitStatus::Rented, $unit->fresh()->status);
        $this->assertTrue($unit->fresh()->currentContract->is($contract));
    }

    public function test_overlapping_contracts_on_the_same_unit_are_rejected(): void
    {
        $unit = $this->makeUnit();
        $client = $this->makeClient();
        $this->contracts->createRent($this->rentAttributes($unit->id), [$client->id => 100]);

        $this->expectExceptionMessage('الوحدة مؤجرة بعقد ساري خلال هذه الفترة');
        $this->contracts->createRent($this->rentAttributes($unit->id, ['start_date' => '2026-06-01', 'end_date' => '2027-05-31']), [$client->id => 100]);
    }

    public function test_party_shares_must_total_100_percent(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->contracts->createRent($this->rentAttributes($this->makeUnit()->id), [$this->makeClient()->id => 60]);
    }

    public function test_future_contract_occupies_the_unit_when_its_start_month_arrives(): void
    {
        $unit = $this->makeUnit();
        $this->contracts->createRent($this->rentAttributes($unit->id, ['start_date' => '2026-03-01', 'end_date' => '2027-02-28']), [$this->makeClient()->id => 100]);
        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);

        Date::setTestNow('2026-03-02');
        $this->assertSame(1, $this->contracts->activateStartedContracts());
        $this->assertSame(UnitStatus::Rented, $unit->fresh()->status);
        $this->assertSame(0, $this->contracts->activateStartedContracts());
    }

    public function test_ending_requires_all_installments_collected_then_frees_the_unit(): void
    {
        $unit = $this->makeUnit();
        $contract = $this->contracts->createRent($this->rentAttributes($unit->id), [$this->makeClient()->id => 100]);

        try {
            $this->contracts->end($contract);
            $this->fail('Ending with unpaid installments must fail');
        } catch (BusinessRuleException $e) {
            $this->assertSame('هذا العقد مرتبط به بعض المبالغ الغير مرحلة', $e->getMessage());
        }

        $contract->installments->each(fn (Installment $i) => app(InstallmentCollector::class)->collect($i));
        $this->contracts->end($contract->fresh());

        $this->assertSame(ContractStatus::Ended, $contract->fresh()->status);
        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
        $this->assertNull($unit->fresh()->current_contract_id);
    }

    public function test_cancelling_deletes_unpaid_installments_but_keeps_collected_and_expenses(): void
    {
        $unit = $this->makeUnit();
        $contract = $this->contracts->createRent($this->rentAttributes($unit->id), [$this->makeClient()->id => 100]);
        app(InstallmentCollector::class)->collect($contract->installments()->first());
        $contract->installments()->create(['unit_id' => $unit->id, 'type' => 'expenses', 'due_date' => '2026-02-01', 'amount' => 90]);

        $this->contracts->cancel($contract);

        $this->assertSame(ContractStatus::Cancelled, $contract->fresh()->status);
        $this->assertSame(2, $contract->installments()->count());
        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
    }

    public function test_sale_contract_marks_unit_sold_and_cannot_sell_twice(): void
    {
        $unit = $this->makeUnit([[100, 100]], ['offer_type' => 'sale']);
        $attributes = ['unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-05', 'total_amount' => 900000,
            'payment_type' => 'once', 'payment_plan' => ['commission' => 2, 'commission_type' => 'percent', 'commission_payer' => 'buyer']];

        $contract = $this->contracts->createSale($attributes, [$this->makeClient()->id => 100]);

        $this->assertSame('SC-2026-00001', $contract->number);
        $this->assertSame('918000.00', $contract->installments()->sole()->amount);
        $this->assertSame(UnitStatus::Sold, $unit->fresh()->status);

        $this->expectExceptionMessage('الوحدة مباعة مسبقًا');
        $this->contracts->createSale($attributes, [$this->makeClient()->id => 100]);
    }
}
