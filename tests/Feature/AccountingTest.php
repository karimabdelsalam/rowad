<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Pages\Accounting\AccountLedger;
use App\Filament\Admin\Pages\Accounting\FinancialStatements;
use App\Filament\Admin\Pages\Accounting\TrialBalance;
use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Admin\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Installment;
use App\Models\JournalEntry;
use App\Models\Lookups\TransactionType;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\Journal;
use App\Services\Accounting\Statements;
use App\Services\Contracts\ContractService;
use App\Services\Finance\VoucherService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Double-entry books: the chart of accounts, entries posted from vouchers and the owner ledger, manual entries, statements. */
class AccountingTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private Owner $owner;

    private Unit $unit;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-10 10:00:00');
        $this->initializeTenancy();

        $this->owner = Owner::create(['first_name' => 'سعد', 'family_name' => 'العتيبي']);
        $this->unit = Unit::create(['title' => 'شقة 1', 'offer_type' => 'rent']);
        $this->unit->owners()->attach($this->owner, ['share_percent' => 100, 'expenses_percent' => 100]);
        $this->bank = BankAccount::create(['holder_name' => 'حساب المؤسسة', 'opening_balance' => 20000, 'balance' => 20000]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{float, float}> account code => [debit, credit] of the document's entry */
    private function entryOf(ReceiptVoucher|PaymentVoucher|OwnerTransaction|BankAccount $source): array
    {
        $entry = JournalEntry::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->sole();

        return $entry->lines()->with('account')->get()
            ->mapWithKeys(fn ($l) => [$l->account->code => [(float) $l->debit, (float) $l->credit]])->sortKeys(SORT_STRING)->all();
    }

    private function balance(string $key): float
    {
        $id = Account::query()->where('system_key', $key)->value('id');

        return (float) app(Statements::class)->trialBalance(null, null, true)->firstWhere(fn ($r) => $r['account']->id === $id)['closing'];
    }

    private function rentInstallment(): Installment
    {
        $contract = app(ContractService::class)->createRent(['unit_id' => $this->unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month',
            'rent_amount' => 1000], [$this->makeClient()->id => 100], notify: false);

        return $contract->installments()->orderBy('due_date')->firstOrFail();
    }

    public function test_every_office_starts_with_a_chart_of_accounts(): void
    {
        $this->assertSame(['1101', '21', '41', '53'], Account::query()->whereIn('system_key', ['cash', 'owners', 'rent_commission', 'general_expenses'])->orderBy('code')->pluck('code')->all());
        $this->assertTrue(Account::query()->where('code', '1')->value('is_group'));

        // a bank account gets its own ledger account under cash and banks, with its opening balance against capital
        $this->assertSame(['1102' => [20000.0, 0.0], '31' => [0.0, 20000.0]], $this->entryOf($this->bank));
        $this->assertSame('حساب المؤسسة', Account::query()->where('bank_account_id', $this->bank->id)->value('name'));
    }

    public function test_rent_collected_is_held_for_the_owner_and_paid_out_to_them(): void
    {
        $installment = $this->rentInstallment();
        $receipt = app(VoucherService::class)->createReceipt(['issued_at' => '2026-01-05', 'amount' => 1000, 'payment_method' => 'cash',
            'party_type' => 'client', 'party_name' => 'عميل تجريبي', 'unit_id' => $this->unit->id, 'reason' => 'إيجار يناير'], [$installment->id]);
        $this->assertSame(['1101' => [1000.0, 0.0], '21' => [0.0, 1000.0]], $this->entryOf($receipt));

        // the office keeps its commission out of the owner's money
        $commission = OwnerTransaction::create(['owner_id' => $this->owner->id, 'unit_id' => $this->unit->id, 'source' => 'rent', 'due_date' => '2026-01-05',
            'transaction_type_id' => TransactionType::where('code', 'commission')->value('id'), 'direction' => 'credit', 'amount' => 50]);
        $this->assertSame(['21' => [50.0, 0.0], '41' => [0.0, 50.0]], $this->entryOf($commission));

        $payout = app(VoucherService::class)->createPayment(['issued_at' => '2026-01-10', 'amount' => 950, 'payment_method' => 'transfer',
            'bank_account_id' => $this->bank->id, 'party_type' => 'owner', 'party_id' => $this->owner->id, 'party_name' => 'سعد العتيبي'], notify: false);
        $this->assertSame(['1102' => [0.0, 950.0], '21' => [950.0, 0.0]], $this->entryOf($payout));
        $this->assertSame($this->owner->id, JournalEntry::query()->where('source_id', $payout->id)->where('source_type', 'payment_voucher')->sole()
            ->lines()->whereNotNull('owner_id')->value('owner_id'));

        $this->assertSame([0.0, 50.0, 19050.0, 1000.0], [$this->balance('owners'), $this->balance('rent_commission'), (float) app(Statements::class)
            ->trialBalance(null, null)->firstWhere(fn ($r) => $r['account']->bank_account_id === $this->bank->id)['closing'], $this->balance('cash')]);
    }

    public function test_expenses_go_to_the_owners_the_tenant_or_the_office(): void
    {
        $vouchers = app(VoucherService::class);
        $owners = $vouchers->createPayment(['issued_at' => '2026-02-01', 'amount' => 300, 'payment_method' => 'cash', 'unit_id' => $this->unit->id, 'reason' => 'سباكة'],
            VoucherService::CHARGE_OWNERS, notify: false);
        $this->assertSame(['1101' => [0.0, 300.0], '21' => [300.0, 0.0]], $this->entryOf($owners));

        $office = $vouchers->createPayment(['issued_at' => '2026-02-02', 'amount' => 100, 'payment_method' => 'cash', 'reason' => 'قرطاسية'], withVat: true, notify: false);
        $this->assertSame(['1101' => [0.0, 115.0], '13' => [15.0, 0.0], '53' => [100.0, 0.0]], $this->entryOf($office));

        $marketing = Account::query()->where('code', '54')->value('id');
        $ads = $vouchers->createPayment(['issued_at' => '2026-02-03', 'amount' => 400, 'payment_method' => 'cash', 'reason' => 'إعلانات', 'account_id' => $marketing], notify: false);
        $this->assertSame(['1101' => [0.0, 400.0], '54' => [400.0, 0.0]], $this->entryOf($ads));

        $this->rentInstallment();
        $tenant = $vouchers->createPayment(['issued_at' => '2026-02-04', 'amount' => 250, 'payment_method' => 'cash', 'unit_id' => $this->unit->id, 'reason' => 'كهرباء'],
            VoucherService::CHARGE_CONTRACT, notify: false);
        $this->assertSame(['1101' => [0.0, 250.0], '1201' => [250.0, 0.0]], $this->entryOf($tenant));

        // the tenant pays the re-billed expense back: the receivable clears
        $rebill = Installment::query()->where('payment_voucher_id', $tenant->id)->sole();
        $back = $vouchers->createReceipt(['issued_at' => '2026-02-20', 'amount' => 250, 'payment_method' => 'cash', 'party_name' => 'عميل تجريبي'], [$rebill->id]);
        $this->assertSame(['1101' => [250.0, 0.0], '1201' => [0.0, 250.0]], $this->entryOf($back));
        $this->assertSame(0.0, $this->balance('tenants'));

        // an unrelated receipt is other income
        $other = $vouchers->createReceipt(['issued_at' => '2026-02-21', 'amount' => 75, 'payment_method' => 'cash', 'party_name' => 'زائر', 'reason' => 'رسوم معاينة']);
        $this->assertSame(['1101' => [75.0, 0.0], '44' => [0.0, 75.0]], $this->entryOf($other));
    }

    public function test_entries_follow_edits_and_deletions(): void
    {
        $voucher = app(VoucherService::class)->createPayment(['issued_at' => '2026-02-02', 'amount' => 100, 'payment_method' => 'cash', 'reason' => 'ضيافة'], notify: false);
        $voucher->update(['amount' => 120, 'bank_account_id' => $this->bank->id]);
        $this->assertSame(['1102' => [0.0, 120.0], '53' => [120.0, 0.0]], $this->entryOf($voucher));
        $number = JournalEntry::query()->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->value('number');

        $voucher->update(['amount' => 130]);
        $this->assertSame($number, JournalEntry::query()->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->value('number'), 'same entry, same number');

        app(VoucherService::class)->delete($voucher);
        $this->assertFalse(JournalEntry::query()->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->exists());
    }

    public function test_manual_entries_must_balance_and_use_postable_accounts(): void
    {
        $cash = Account::query()->where('system_key', 'cash')->value('id');
        $capital = Account::query()->where('system_key', 'capital')->value('id');
        $entry = app(Journal::class)->post(['entry_date' => '2026-01-01', 'description' => 'رأس مال نقدي'], [
            ['account_id' => $cash, 'debit' => 5000], ['account_id' => $capital, 'credit' => 5000],
        ]);
        $this->assertSame(['JE-2026-00002', true, 5000.0], [$entry->number, $entry->isManual(), (float) $entry->total], 'the bank opening balance took number 1');

        foreach ([
            [['account_id' => $cash, 'debit' => 100], ['account_id' => $capital, 'credit' => 90]],
            [['account_id' => $cash, 'debit' => 100], ['account_id' => Account::query()->where('code', '1')->value('id'), 'credit' => 100]],
            [['account_id' => $cash, 'debit' => 100]],
        ] as $lines) {
            try {
                app(Journal::class)->post(['entry_date' => '2026-01-01', 'description' => 'خطأ'], $lines);
                $this->fail('rejected: '.json_encode($lines));
            } catch (BusinessRuleException) {
                $this->assertSame(2, JournalEntry::count());
            }
        }

        // rebuilding re-posts the documents and keeps the manual entries
        app(Journal::class)->rebuild();
        $this->assertSame(2, JournalEntry::count());
        $this->assertTrue(JournalEntry::query()->whereKey($entry->id)->exists());
    }

    public function test_trial_balance_and_statements_balance(): void
    {
        $installment = $this->rentInstallment();
        $vouchers = app(VoucherService::class);
        $vouchers->createReceipt(['issued_at' => '2026-01-05', 'amount' => 1000, 'payment_method' => 'cash', 'party_name' => 'عميل'], [$installment->id]);
        OwnerTransaction::create(['owner_id' => $this->owner->id, 'unit_id' => $this->unit->id, 'source' => 'build', 'due_date' => '2026-01-31',
            'transaction_type_id' => TransactionType::where('code', 'mancost')->value('id'), 'direction' => 'credit', 'amount' => 200]);
        $vouchers->createPayment(['issued_at' => '2026-02-02', 'amount' => 80, 'payment_method' => 'cash', 'reason' => 'ضيافة'], notify: false);

        $rows = app(Statements::class)->trialBalance('2026-01-01', '2026-12-31')->reject(fn ($r) => $r['account']->is_group);
        $this->assertEqualsWithDelta($rows->sum('debit'), $rows->sum('credit'), 0.001);
        $this->assertSame(21280.0, round($rows->sum('debit'), 2));

        $income = app(Statements::class)->incomeStatement('2026-01-01', '2026-12-31');
        $this->assertSame([200.0, 80.0, 120.0], [$income['total_revenue'], $income['total_expenses'], $income['net']]);

        $sheet = app(Statements::class)->balanceSheet('2026-12-31');
        $this->assertSame(20920.0, $sheet['total_assets']);
        $this->assertSame($sheet['total_assets'], round($sheet['total_liabilities'] + $sheet['total_equity'], 2));

        $ledger = app(Statements::class)->ledger(Account::query()->where('system_key', 'owners')->firstOrFail(), '2026-01-01', '2026-01-31');
        $this->assertSame([0.0, 800.0, [1000.0, 800.0]], [$ledger['opening'], $ledger['closing'], $ledger['lines']->pluck('balance')->all()]);
    }

    public function test_the_accounting_screens(): void
    {
        $this->actingAs(User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('admin');
        app(VoucherService::class)->createPayment(['issued_at' => '2026-02-02', 'amount' => 80, 'payment_method' => 'cash', 'reason' => 'ضيافة'], notify: false);

        Livewire::test(TrialBalance::class)->assertOk()->assertSee(__('الميزان متوازن'))->assertSee(__('مصروفات تشغيلية عامة'));
        Livewire::test(AccountLedger::class, ['account' => Account::query()->where('system_key', 'cash')->value('id')])->assertOk()->assertSee('ضيافة');
        Livewire::test(FinancialStatements::class)->assertOk()->assertSee(__('صافي الخسارة'))->set('tab', 'balance')->assertSee(__('إجمالي الأصول'));

        $cash = Account::query()->where('system_key', 'cash')->value('id');
        $capital = Account::query()->where('system_key', 'capital')->value('id');
        Livewire::test(CreateJournalEntry::class)
            ->fillForm(['entry_date' => '2026-03-01', 'description' => 'إيداع رأس مال', 'lines' => [
                ['account_id' => $cash, 'debit' => 1000, 'credit' => null, 'memo' => null],
                ['account_id' => $capital, 'debit' => null, 'credit' => 1000, 'memo' => null],
            ]])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(1000.0, (float) JournalEntry::query()->whereNull('source_type')->sole()->total);

        // an agent sees none of it
        $agent = User::create(['type' => 'staff', 'name' => 'مسوق', 'email' => 'agent@test.local', 'password' => 'secret-password']);
        $agent->assignRole('agent');
        $this->actingAs($agent);
        $this->assertFalse(TrialBalance::canAccess());
        $this->assertFalse(JournalEntryResource::canViewAny());
    }
}
