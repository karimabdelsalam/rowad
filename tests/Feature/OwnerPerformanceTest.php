<?php

namespace Tests\Feature;

use App\Filament\Owner\Pages\Performance;
use App\Services\Insights\OwnerPerformance;
use App\Services\Installments\InstallmentCollector;
use App\Services\Installments\InstallmentScheduler;
use App\Services\Portal\PortalAccounts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The owner's portfolio page: net income per property, occupancy, arrears and yield. */
class OwnerPerformanceTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-04-15');
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_net_income_occupancy_arrears_and_yield_per_property(): void
    {
        // the owner holds 50% of a flat let from 1 Feb, 1,000 a month; Feb and Mar paid, Apr not yet
        $unit = $this->makeUnit([[50, 50], [50, 50]], ['market_value' => 400000]);
        $contract = $this->makeRentContract(['start_date' => '2026-02-01', 'contract_date' => '2026-02-01', 'end_date' => '2027-01-31'], $unit);
        $installments = app(InstallmentScheduler::class)->schedule($contract)->sortBy('due_date')->values();
        app(InstallmentCollector::class)->collect($installments[0]);
        app(InstallmentCollector::class)->collect($installments[1]);
        $owner = $unit->owners()->orderBy('id')->first();

        $report = app(OwnerPerformance::class)->forOwner($owner, 2026);
        $row = $report['units'][0];
        $this->assertSame(50.0, $row['share']);
        $this->assertSame(1000.0, $row['gross']);           // 50% of two months
        $this->assertSame(500.0, $row['arrears']);          // 50% of April, due and unpaid
        $this->assertSame(200000.0, $row['value']);         // 50% of the market value
        $days = 105;                                        // 1 Jan – 15 Apr
        $this->assertSame(round(74 / $days * 100, 1), $row['occupancy']); // let from 1 Feb
        $this->assertSame($days - 74, $row['vacant_days']);
        $this->assertSame(round($row['net'] / $days * 365 / 200000 * 100, 2), $row['yield']);
        $this->assertSame($row['net'], $report['totals']['net']);

        // the owner sees it in the portal; a past year without data is empty but works
        $user = app(PortalAccounts::class)->issue($owner, 'owner1', 'secret-password')['user'];
        Filament::setCurrentPanel('owner');
        $this->actingAs($user);
        Livewire::test(Performance::class)->assertOk()->assertSee('أداء أملاكي')->assertSee('شقة 101')->assertSee('1,000.00')->assertSee('500.00')
            ->set('year', 2025)->assertOk()->assertSee('0.00');
    }
}
