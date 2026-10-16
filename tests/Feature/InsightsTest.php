<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Installment;
use App\Models\RentContract;
use App\Models\User;
use App\Services\Ai\Tools\StaffTools;
use App\Services\Insights\PaymentRisk;
use App\Services\Insights\RentAdvisor;
use App\Services\Installments\InstallmentCollector;
use App\Services\Installments\InstallmentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Predictive layer: late-payment risk from history, and a fair rent from comparable contracts. */
class InsightsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-07-20');
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    /** A monthly 2026 contract whose installments up to today were paid $lateDays after the due date (null = left unpaid). */
    private function history(array $lateDays, array $unit = []): RentContract
    {
        $contract = $this->makeRentContract([], $this->makeUnit([[100, 100]], $unit));
        $installments = app(InstallmentScheduler::class)->schedule($contract)->sortBy('due_date')->values();
        foreach ($installments->filter(fn (Installment $i) => $i->due_date->lte(today()))->values() as $n => $installment) {
            if (array_key_exists($n, $lateDays) && $lateDays[$n] === null) {
                continue; // left unpaid
            }
            $days = $lateDays[$n] ?? 0;
            Date::setTestNow($installment->due_date->copy()->addDays($days)->setTime(10, 0));
            app(InstallmentCollector::class)->collect($installment->fresh());
        }
        Date::setTestNow('2026-07-20');

        return $contract->fresh();
    }

    public function test_a_punctual_tenant_is_low_risk_and_a_new_one_has_no_verdict(): void
    {
        $risk = app(PaymentRisk::class)->forClient($this->history([0, 1, 0, 2, 0, 0, 0])->clients->first());
        $this->assertSame('low', $risk['level']);
        $this->assertLessThan(30, $risk['score']);
        $this->assertStringContainsString('يسدد في موعده', $risk['reasons'][0]);

        $newcomer = $this->makeRentContract(['start_date' => '2026-07-15', 'contract_date' => '2026-07-15', 'end_date' => '2027-07-14']);
        app(InstallmentScheduler::class)->schedule($newcomer);
        $this->assertSame('new', app(PaymentRisk::class)->forClient($newcomer->clients->first())['level']);
    }

    public function test_a_late_payer_with_arrears_is_high_risk_and_tops_the_list(): void
    {
        $this->history([0, 0, 0, 0, 0, 0, 0]); // punctual neighbour
        $late = $this->history([20, 15, 30, 12, 25, null, null]); // always late, two months unpaid now

        $risk = app(PaymentRisk::class)->forClient($late->clients->first());
        $this->assertSame('high', $risk['level']);
        $this->assertGreaterThanOrEqual(60, $risk['score']);
        $this->assertSame(2, $risk['stats']['overdue_now']);
        $this->assertSame(7, $risk['stats']['late']);
        $this->assertStringContainsString('دفعات متأخرة الآن', $risk['reasons'][0]);

        $list = app(PaymentRisk::class)->atRisk();
        $this->assertCount(1, $list);
        $this->assertSame($late->clients->first()->id, $list[0]['client']->id);

        // staff see it on the client page and the dashboard; the assistant can ask for it
        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->get("{$this->host}/admin/clients/{$late->clients->first()->id}")->assertOk()->assertSee('سلوك السداد')->assertSee('مرتفع');
        $this->get("{$this->host}/admin")->assertOk();
        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\TenantsAtRisk::class)->assertSee('مستأجرون معرّضون للتعثر')->assertSee($late->clients->first()->displayName());
        $tool = collect(StaffTools::all())->firstWhere('name', 'payment_risk')->run([], $admin);
        $this->assertSame('high', $tool['tenants'][0]['level']);
    }

    public function test_rent_suggestion_comes_from_comparable_units_only(): void
    {
        $riyadh = City::create(['name' => 'الرياض', 'country_code' => 'SA']);
        $flat = fn (int $rooms, float $area) => ['city_id' => $riyadh->id, 'rooms' => $rooms, 'area' => $area];
        foreach ([[3, 140, 42000], [3, 150, 45000], [2, 120, 36000], [4, 160, 48000]] as [$rooms, $area, $yearly]) {
            $this->makeRentContract(['yearly_rent' => $yearly, 'start_date' => '2026-01-01'], $this->makeUnit([[100, 100]], ['title' => "شقة {$area}"] + $flat($rooms, $area)));
        }
        // not comparable: a shop (no rooms), a villa (too big), an old contract, a flat in another city with another category
        $this->makeRentContract(['yearly_rent' => 90000], $this->makeUnit([[100, 100]], ['title' => 'محل', 'city_id' => $riyadh->id, 'area' => 140]));
        $this->makeRentContract(['yearly_rent' => 200000], $this->makeUnit([[100, 100]], ['title' => 'فيلا'] + $flat(3, 450)));
        $this->makeRentContract(['yearly_rent' => 10000, 'start_date' => '2023-01-01', 'end_date' => '2023-12-31', 'contract_date' => '2023-01-01'], $this->makeUnit([[100, 100]], $flat(3, 145)));

        $target = $this->makeUnit([[100, 100]], ['title' => 'شقة جديدة', 'rent_amount' => 36000] + $flat(3, 145));
        $advice = app(RentAdvisor::class)->forUnit($target);
        $this->assertSame('city', $advice['basis']);
        $this->assertSame(4, $advice['sample']);
        $this->assertSame('medium', $advice['confidence']);
        $this->assertEqualsWithDelta(43500, $advice['suggested'], 1500); // ~300 SAR/m² × 145 m²
        $this->assertLessThanOrEqual($advice['suggested'], $advice['low']);
        $this->assertGreaterThanOrEqual($advice['suggested'], $advice['high']);
        $this->assertLessThan(-5, $advice['gap_percent']); // listed below the market

        // a shop has only one comparable shop: not enough to say anything; units for sale get nothing
        $this->assertNull(app(RentAdvisor::class)->forUnit($this->makeUnit([[100, 100]], ['title' => 'محل 2', 'city_id' => $riyadh->id, 'area' => 120])));
        $this->assertNull(app(RentAdvisor::class)->forUnit($this->makeUnit([[100, 100]], ['offer_type' => 'sale'] + $flat(3, 145))));

        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->get("{$this->host}/admin/units/{$target->id}")->assertOk()->assertSee('السعر المقترح')->assertSee('أقل من السوق');
        $this->assertSame(4, collect(StaffTools::all())->firstWhere('name', 'suggest_rent')->run(['unit_id' => $target->id], $admin)['sample']);
    }
}
