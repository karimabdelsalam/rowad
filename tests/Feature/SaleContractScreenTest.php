<?php

namespace Tests\Feature;

use App\Enums\UnitStatus;
use App\Filament\Admin\Resources\SaleContracts\Pages\CreateSaleContract;
use App\Models\SaleContract;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SaleContractScreenTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function base(array $extra): array
    {
        $unit = $this->makeUnit([[100, 100]], ['offer_type' => 'sale', 'title' => 'فيلا للبيع']);

        return $extra + [
            'unit_id' => $unit->id,
            'parties' => [['client_id' => $this->makeClient()->id, 'share_percent' => 100]],
            'calendar' => 'gregorian',
            'contract_date' => '2026-03-01',
            'total_amount' => 1200000,
        ];
    }

    public function test_single_payment_with_percentage_commission(): void
    {
        Livewire::test(CreateSaleContract::class)
            ->fillForm($this->base(['payment_type' => 'once']))
            ->fillForm(['payment_plan' => ['commission' => 2.5, 'commission_type' => 'percent', 'commission_payer' => 'buyer']])
            ->call('create')->assertHasNoFormErrors();

        $contract = SaleContract::sole();
        $this->assertSame('1230000.00', $contract->installments()->sole()->amount);
        $this->assertSame(UnitStatus::Sold, $contract->unit->status);
    }

    public function test_staged_payments_with_deferred_cheque(): void
    {
        Livewire::test(CreateSaleContract::class)
            ->fillForm($this->base(['payment_type' => 'parts']))
            ->fillForm(['payment_plan' => ['commission_payer' => 'seller', 'parts' => [
                ['amount' => 700000, 'commission' => 10000, 'received_date' => '2026-03-01', 'deferred' => false],
                ['amount' => 500000, 'commission' => 0, 'received_date' => '2026-03-01', 'deferred' => true, 'due_date' => '2026-09-01', 'cheque_number' => '000123'],
            ]]])
            ->call('create')->assertHasNoFormErrors();

        $rows = SaleContract::sole()->installments()->orderBy('due_date')->get();
        $this->assertSame(['700000.00', '500000.00'], $rows->pluck('amount')->all());
        $this->assertSame('10000.00', $rows[0]->seller_commission);
        $this->assertSame('2026-09-01', $rows[1]->due_date->toDateString());
    }

    public function test_installments_after_down_payment(): void
    {
        Livewire::test(CreateSaleContract::class)
            ->fillForm($this->base(['payment_type' => 'installment']))
            ->fillForm(['payment_plan' => ['first_payment' => 200000, 'count' => 20, 'cycle' => 'quarter', 'start_date' => '2026-03-01']])
            ->call('create')->assertHasNoFormErrors();

        $rows = SaleContract::sole()->installments()->orderBy('due_date')->get();
        $this->assertCount(20, $rows);
        $this->assertSame('50000.00', $rows[0]->amount);
        $this->assertSame('2026-06-01', $rows[0]->due_date->toDateString());
    }

    public function test_parts_not_matching_total_are_rejected(): void
    {
        Livewire::test(CreateSaleContract::class)
            ->fillForm($this->base(['payment_type' => 'parts']))
            ->fillForm(['payment_plan' => ['commission_payer' => 'buyer', 'parts' => [
                ['amount' => 100000, 'commission' => 0, 'received_date' => '2026-03-01', 'deferred' => false],
            ]]])
            ->call('create')
            ->assertNotified('خطأ! اجمالي مبالغ الدفعات لا يساوي اجمالي قيمة العقد');

        $this->assertSame(0, SaleContract::count());
    }
}
