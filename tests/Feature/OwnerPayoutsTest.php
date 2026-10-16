<?php

namespace Tests\Feature;

use App\Enums\OwnerPayoutStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\OwnerPayouts\Pages\PrepareOwnerPayout;
use App\Filament\Admin\Resources\OwnerPayouts\Pages\ViewOwnerPayout;
use App\Filament\Admin\Resources\Owners\Pages\EditOwner;
use App\Models\BankAccount;
use App\Models\Lookups\Bank;
use App\Models\OutgoingMessage;
use App\Models\Owner;
use App\Models\OwnerPayout;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\Unit;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Finance\VoucherService;
use App\Services\Import\Spreadsheet;
use App\Services\Installments\InstallmentCollector;
use App\Services\Payouts\OwnerPayouts;
use App\Support\Iban;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Paying many owners their net dues in one run: vouchers, bank file, confirmation and cancellation. */
class OwnerPayoutsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private const RAJHI = 'SA2180000000608010167001';

    private const SNB = 'SA5510000000012345670002';

    private Owner $saad;

    private Owner $noura;

    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-10 10:00:00');
        $this->initializeTenancy();

        // Saad: two months of rent collected, a maintenance bill charged to him; Noura: one month, no IBAN yet
        $this->saad = $this->ownerWithRent('سعد', 'العتيبي', 'شقة 1', 2, ['iban' => self::RAJHI, 'mobile' => '0501112233']);
        $this->noura = $this->ownerWithRent('نورة', 'القحطاني', 'شقة 2', 1, ['mobile' => '0502223344']);
        app(VoucherService::class)->createPayment(['issued_at' => '2026-02-10', 'amount' => 300, 'payment_method' => 'cash',
            'unit_id' => Unit::where('title', 'شقة 1')->value('id'), 'reason' => 'صيانة'], VoucherService::CHARGE_OWNERS);

        $this->account = BankAccount::create(['holder_name' => 'حساب المؤسسة', 'iban' => 'SA0380000000608010167519', 'balance' => 50000]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_iban_validation_and_bank_recognition(): void
    {
        $this->assertTrue(Iban::valid('sa21 8000 0000 6080 1016 7001'));
        $this->assertTrue(Iban::valid('SA٢١80000000608010167001'), 'Arabic-Indic digits');
        $this->assertFalse(Iban::valid('SA2180000000608010167002'), 'check digits');
        $this->assertFalse(Iban::valid('SA218000000060801016700'), 'too short');
        $this->assertSame(['مصرف الراجحي', 'RJHISARI'], Iban::bank(self::RAJHI));
        $this->assertSame('البنك الأهلي السعودي', Iban::bank(self::SNB)[0]);
        $this->assertSame('SA21 8000 0000 6080 1016 7001', Iban::format(self::RAJHI));
    }

    public function test_candidates_net_each_owner_up_to_the_date(): void
    {
        $rows = app(OwnerPayouts::class)->candidates('2026-03-10')->keyBy(fn ($row) => $row['owner']->id);
        $this->assertSame([2000.0, 300.0, 1700.0, true, 'مصرف الراجحي'],
            [$rows[$this->saad->id]['dues'], $rows[$this->saad->id]['deductions'], $rows[$this->saad->id]['net'], $rows[$this->saad->id]['ready'], $rows[$this->saad->id]['bank']]);
        $this->assertSame([1000.0, false], [$rows[$this->noura->id]['net'], $rows[$this->noura->id]['ready']]);

        $january = app(OwnerPayouts::class)->candidates('2026-01-15')->keyBy(fn ($row) => $row['owner']->id);
        $this->assertSame([1000.0, 0.0], [$january[$this->saad->id]['dues'], $january[$this->saad->id]['deductions']], 'February rent and expense are later');

        $this->assertCount(1, app(OwnerPayouts::class)->candidates('2026-03-10', 1500), 'minimum amount');
    }

    public function test_a_run_pays_net_dues_and_owners_hear_only_after_the_transfer(): void
    {
        try {
            app(OwnerPayouts::class)->prepare([$this->saad->id, $this->noura->id], '2026-03-10', $this->account);
            $this->fail('an owner without an IBAN cannot be in the run');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('نورة', $e->getMessage());
        }

        $payout = app(OwnerPayouts::class)->prepare([$this->saad->id], '2026-03-10', $this->account, '2026-03-10');
        $this->assertSame('OP-2026-00001', $payout->number);
        $this->assertSame([1, 1700.0], [$payout->owners_count, (float) $payout->total]);
        $item = $payout->items()->sole();
        $this->assertSame([2000.0, 300.0, 1700.0, 3], [(float) $item->dues, (float) $item->deductions, (float) $item->amount, $item->entries_count]);

        $voucher = $item->paymentVoucher;
        $this->assertSame([1700.0, 'transfer', $this->saad->id], [(float) $voucher->amount, $voucher->payment_method->value ?? $voucher->payment_method, $voucher->party_id]);
        $this->assertSame(0.0, $this->saad->outstandingBalance());
        $this->assertSame(0, OwnerTransaction::where('owner_id', $this->saad->id)->where('is_settled', false)->count());
        $this->assertSame(48300.0, (float) $this->account->fresh()->balance);
        $this->assertSame(0, OutgoingMessage::where('event', 'owner_payout')->count(), 'not before the bank transfers');

        $rows = Spreadsheet::rows(app(OwnerPayouts::class)->bankFile($payout), 'xlsx');
        $this->assertSame([self::RAJHI, '1700.00', 'RJHISARI', 'سعد العتيبي'], [$rows[0]['رقم الآيبان'], $rows[0]['المبلغ'], $rows[0]['رمز السويفت'], $rows[0]['اسم المستفيد']]);
        $csv = file_get_contents(app(OwnerPayouts::class)->bankFile($payout, 'csv'));
        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertSame([self::RAJHI, 'مصرف الراجحي', 'RJHISARI', '1700.00'], array_slice(str_getcsv(explode("\n", $csv)[1], escape: ''), 2, 4));

        $this->expectNothingLeftFor($this->saad);

        app(OwnerPayouts::class)->markTransferred($payout);
        $this->assertSame(OwnerPayoutStatus::Transferred, $payout->fresh()->status);
        $this->assertSame('0501112233', OutgoingMessage::where('event', 'owner_payout')->where('channel', 'sms')->value('recipient'));

        $this->expectException(BusinessRuleException::class);
        app(OwnerPayouts::class)->cancel($payout->fresh());
    }

    public function test_cancelling_reopens_the_dues(): void
    {
        $payout = app(OwnerPayouts::class)->prepare([$this->saad->id], '2026-03-10', $this->account);
        $voucherId = $payout->items()->value('payment_voucher_id');

        app(OwnerPayouts::class)->cancel($payout);
        $this->assertSame(OwnerPayoutStatus::Cancelled, $payout->fresh()->status);
        $this->assertSoftDeleted(PaymentVoucher::class, ['id' => $voucherId]);
        $this->assertSame(1700.0, $this->saad->outstandingBalance(), 'rent shares and the expense charge are open again');
        $this->assertSame(50000.0, (float) $this->account->fresh()->balance);
        $this->assertSame(1700.0, app(OwnerPayouts::class)->candidates('2026-03-10')->firstWhere('owner.id', $this->saad->id)['net']);
    }

    public function test_staff_prepare_a_run_from_the_screen_and_confirm_it(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(PrepareOwnerPayout::class)
            ->assertOk()
            ->assertSee('سعد العتيبي')->assertSee('نورة القحطاني')->assertSee(__('لا يوجد آيبان'))
            ->assertSet('selected', [$this->saad->id])
            ->set('data.bank_account_id', $this->account->id)
            ->call('create')
            ->assertRedirect();
        $payout = OwnerPayout::sole();

        Livewire::test(ViewOwnerPayout::class, ['record' => $payout->getKey()])
            ->assertOk()->assertSee($payout->number)
            ->callAction('bankFile')->assertFileDownloaded($payout->number.'.xlsx')
            ->callAction('transferred');
        $this->assertSame(OwnerPayoutStatus::Transferred, $payout->fresh()->status);
    }

    public function test_the_owner_form_checks_the_iban_and_fills_the_bank(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(EditOwner::class, ['record' => $this->noura->getKey()])
            ->fillForm(['iban' => 'SA55 1000 0000 0123 4567 0003'])->call('save')
            ->assertHasFormErrors(['iban']);

        Livewire::test(EditOwner::class, ['record' => $this->noura->getKey()])
            ->fillForm(['iban' => 'sa55 1000 0000 0123 4567 0002'])
            ->assertSet('data.bank_id', Bank::where('title', 'البنك الأهلي السعودي')->value('id'))
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(self::SNB, $this->noura->fresh()->iban);
    }

    public function test_staff_without_the_permission_cannot_open_payouts(): void
    {
        $this->actingAs(User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']));
        Filament::setCurrentPanel('admin');

        $this->assertFalse(PrepareOwnerPayout::canAccess());
    }

    private function expectNothingLeftFor(Owner $owner): void
    {
        try {
            app(OwnerPayouts::class)->prepare([$owner->id], '2026-03-10', $this->account);
            $this->fail('the same dues cannot be paid twice');
        } catch (BusinessRuleException) {
            $this->assertSame(1, OwnerPayout::count());
        }
    }

    private function admin(): User
    {
        return User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    /** A unit owned 100% by a new owner, rented from January at 1,000 a month, with the first months collected. */
    private function ownerWithRent(string $first, string $family, string $unitTitle, int $collected, array $attributes): Owner
    {
        $owner = Owner::create(['first_name' => $first, 'family_name' => $family] + $attributes);
        $unit = Unit::create(['title' => $unitTitle, 'offer_type' => 'rent']);
        $unit->owners()->attach($owner, ['share_percent' => 100, 'expenses_percent' => 100]);
        $contract = app(ContractService::class)->createRent(['unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month',
            'rent_amount' => 1000], [$this->makeClient()->id => 100], notify: false);
        foreach ($contract->installments()->orderBy('due_date')->limit($collected)->get() as $installment) {
            app(InstallmentCollector::class)->collect($installment);
        }

        return $owner;
    }
}
