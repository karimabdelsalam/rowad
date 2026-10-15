<?php

namespace Tests\Feature;

use App\Filament\Admin\Clusters\Reports\Pages;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Finance\VoucherService;
use App\Services\Installments\InstallmentCollector;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-06-15');
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function seedActivity(): array
    {
        Settings::set('income_tax_enabled', true);
        Settings::set('income_tax_percent', 10);
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة التقارير']);
        $client = $this->makeClient();
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter', 'rent_amount' => 4600,
            'vat_applicable' => true, 'vat_percent' => 15, 'vat_amount' => 600, 'commission' => 1000, 'commission_cycle' => 'once',
        ], [$client->id => 100]);
        [$q1, $q2] = $contract->installments()->orderBy('due_date')->take(2)->get()->all();
        app(VoucherService::class)->createReceipt(['issued_at' => '2026-01-05', 'amount' => 5600, 'payment_method' => 'cash', 'party_type' => 'client', 'party_id' => $client->id], [$q1->id]);
        app(VoucherService::class)->createReceipt(['issued_at' => '2026-04-05', 'amount' => 4600, 'payment_method' => 'transfer'], [$q2->id]);
        app(VoucherService::class)->createPayment(['issued_at' => '2026-04-20', 'amount' => 800, 'unit_id' => $unit->id, 'reason' => 'صيانة'], VoucherService::CHARGE_OWNERS);

        return compact('unit', 'client', 'contract');
    }

    private function actingAsRole(string $role): void
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@office.test", 'password' => 'secret-password']);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_every_report_renders_for_the_accountant(): void
    {
        $this->seedActivity();
        $this->actingAsRole('accountant');

        foreach ([Pages\IncomeReport::class, Pages\ExpensesReport::class, Pages\ProfitsReport::class, Pages\BankingReport::class,
            Pages\TaxesReport::class, Pages\CorporateTaxesReport::class, Pages\UnitReport::class, Pages\UnitsReport::class,
            Pages\RentReport::class, Pages\RenterReport::class] as $page) {
            $this->get($this->host.parse_url($page::getUrl(panel: 'admin'), PHP_URL_PATH))->assertOk();
        }
    }

    public function test_income_and_profits_figures(): void
    {
        $this->seedActivity();
        $this->actingAsRole('accountant');

        Livewire::test(Pages\IncomeReport::class)
            ->assertCanSeeTableRecords(\App\Models\ReceiptVoucher::all())
            ->assertTableColumnSummarySet('amount', 'sum', 10200);

        Livewire::test(Pages\IncomeReport::class)
            ->filterTable('payment_method', 'transfer')
            ->assertTableColumnSummarySet('amount', 'sum', 4600);

        $total = Livewire::test(Pages\ProfitsReport::class)->instance()->getTableRecords()['total'];
        $this->assertSame([10200.0, 800.0, 9400.0], [$total['income'], $total['expenses'], $total['net']]);
    }

    public function test_taxes_units_and_renter_reports(): void
    {
        $data = $this->seedActivity();
        $this->actingAsRole('accountant');

        Livewire::test(Pages\TaxesReport::class)->assertTableColumnSummarySet('amount', 'sum', 1200);       // VAT 600 x 2 quarters
        Livewire::test(Pages\CorporateTaxesReport::class)->assertTableColumnSummarySet('amount', 'sum', 100); // 10% of the 1000 renter commission
        $this->assertSame(10200.0, Livewire::test(Pages\UnitsReport::class)->instance()->getTableRecords()['total']['rent']);

        Livewire::test(Pages\RenterReport::class)
            ->assertCountTableRecords(0)                                   // nothing until a client is chosen
            ->filterTable('client', ['client_id' => $data['client']->id])
            ->assertCountTableRecords(2)                                   // default period: start of year → today (Jun 15)
            ->filterTable('period', ['from' => '2026-01-01', 'until' => '2026-12-31'])
            ->assertCountTableRecords(4)
            ->assertTableColumnSummarySet('amount', 'sum', 19400);
    }

    public function test_csv_export_uses_current_filters(): void
    {
        $this->seedActivity();
        $this->actingAsRole('accountant');

        $component = Livewire::test(Pages\IncomeReport::class)->filterTable('payment_method', 'cash');
        $response = $component->instance()->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('5600.00', str_replace(',', '', $csv));
        $this->assertStringNotContainsString('4600.00', str_replace(',', '', $csv));
    }

    public function test_agents_cannot_open_financial_reports(): void
    {
        $this->actingAsRole('agent');

        $this->get($this->host.parse_url(Pages\IncomeReport::getUrl(panel: 'admin'), PHP_URL_PATH))->assertForbidden();
    }
}
