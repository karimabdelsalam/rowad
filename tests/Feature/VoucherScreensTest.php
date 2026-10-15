<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentVouchers\Pages\CreatePaymentVoucher;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\CreateReceiptVoucher;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\EditReceiptVoucher;
use App\Models\BankAccount;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use App\Services\Contracts\ContractService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class VoucherScreensTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
        $user = User::create(['name' => 'محاسب', 'email' => 'acc@office.test', 'password' => 'secret-password']);
        $user->assignRole('accountant');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_receipt_screen_links_installments_and_fills_the_amount(): void
    {
        $unit = $this->makeUnit();
        $client = $this->makeClient();
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'half_year', 'rent_amount' => 9000,
        ], [$client->id => 100]);
        $bank = BankAccount::create(['holder_name' => 'الراجحي', 'balance' => 0]);
        $first = $contract->installments()->orderBy('due_date')->first();

        Livewire::test(CreateReceiptVoucher::class)
            ->fillForm(['party_type' => 'client', 'party_id' => $client->id, 'unit_id' => $unit->id])
            ->fillForm(['installment_ids' => [$first->id]])
            ->assertSchemaStateSet(['amount' => 9000.0])
            ->fillForm(['payment_method' => 'transfer', 'bank_account_id' => $bank->id, 'reason' => 'الدفعة الأولى'])
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = ReceiptVoucher::sole();
        $this->assertTrue($voucher->party->is($client));
        $this->assertTrue($first->fresh()->is_collected);
        $this->assertSame('9000.00', $bank->fresh()->balance);

        $other = BankAccount::create(['holder_name' => 'الأهلي', 'balance' => 0]);
        Livewire::test(EditReceiptVoucher::class, ['record' => $voucher->getRouteKey()])
            ->fillForm(['bank_account_id' => $other->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['0.00', '9000.00'], [$bank->fresh()->balance, $other->fresh()->balance]);
    }

    public function test_payment_screen_charges_expense_to_owners(): void
    {
        $unit = $this->makeUnit([[60, 60], [40, 40]]);

        Livewire::test(CreatePaymentVoucher::class)
            ->fillForm(['party_name' => 'مؤسسة الصيانة', 'unit_id' => $unit->id])
            ->fillForm(['charge_to' => 'owners', 'amount' => 500, 'payment_method' => 'cash', 'reason' => 'سباكة'])
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = PaymentVoucher::sole();
        $this->assertSame('مؤسسة الصيانة', $voucher->party_name);
        $this->assertEqualsCanonicalizing(['300.00', '200.00'], OwnerTransaction::where('payment_voucher_id', $voucher->id)->pluck('amount')->all());
    }
}
