<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** A small legacy database (same table/column names as the old system) imported through rowad:import-legacy. */
class LegacyImportTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private string $legacyFile;

    private string $mediaDir;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = storage_path('framework/testing/legacy-'.uniqid());
        File::ensureDirectoryExists($dir.'/media/building');
        $this->legacyFile = $dir.'/legacy.sqlite';
        $this->mediaDir = $dir.'/media';
        touch($this->legacyFile);
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => $this->legacyFile, 'prefix' => '']]);
        $this->seedLegacy();
        $photo = UploadedFile::fake()->image('front.jpg', 20, 20);
        File::copy($photo->getPathname(), $this->mediaDir.'/building/front.jpg');

        $this->tenant = $this->createTenant();
    }

    protected function tearDown(): void
    {
        DB::purge('legacy');
        File::deleteDirectory(dirname($this->legacyFile));
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function import(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('rowad:import-legacy', $options + [
            'tenant' => $this->tenant->id, '--driver' => 'sqlite', '--database' => $this->legacyFile, '--media' => $this->mediaDir,
        ]);
    }

    public function test_office_data_is_imported_with_references_intact(): void
    {
        $this->import()->assertSuccessful();
        tenancy()->initialize($this->tenant);

        // settings
        $this->assertSame('مكتب الرواد', Settings::get('site_name'));
        $this->assertEquals(15, Settings::get('vat_percent'));
        $this->assertSame('hijri', Settings::get('system_calendar'));

        // units, ownership shares, meters, photos
        $unit = Unit::with(['owners', 'meters'])->findOrFail(100);
        $this->assertSame('برج الرواد', $unit->building->title);
        $this->assertSame(['10' => 60.0, '11' => 40.0], $unit->owners->mapWithKeys(fn ($o) => [(string) $o->id => (float) $o->pivot->share_percent])->all());
        $this->assertSame('MTR-1', $unit->meters->first()->number);
        $this->assertEqualsWithDelta(24.7, $unit->latitude, 0.0001);
        $this->assertSame('rented', $unit->status->value ?? $unit->status);
        $this->assertCount(1, $unit->getMedia('photos'));

        // rent contract, its tenant and clauses, and it is the unit's current contract
        $contract = RentContract::with(['clients', 'terms'])->findOrFail(50);
        $this->assertSame([20], $contract->clients->pluck('id')->all());
        $this->assertCount(2, $contract->terms);
        $this->assertSame(['rent_contract', 50], [$unit->current_contract_type, (int) $unit->current_contract_id]);
        $this->assertSame('خالد الحربي', $contract->clients->first()->full_name);

        // sale contract keeps its payment plan
        $sale = SaleContract::findOrFail(60);
        $this->assertSame('buyer', $sale->payment_plan['commission_payer']);
        $this->assertSame('percent', $sale->payment_plan['commission_type']);

        // installments: collected with its receipt voucher, pending one keeps its reminder stage
        $paid = Installment::findOrFail(1000);
        $this->assertTrue($paid->is_collected);
        $this->assertSame(300, (int) $paid->receipt_voucher_id);
        $this->assertSame(1, (int) Installment::findOrFail(1001)->reminder_stage);

        // vouchers keep their party
        $voucher = ReceiptVoucher::findOrFail(300);
        $this->assertSame(['client', 20], [$voucher->party_type, (int) $voucher->party_id]);

        // owner ledger: legacy codes, settlement, office-side child entry
        $entry = OwnerTransaction::with('type')->findOrFail(2000);
        $this->assertSame(['payment', 'debit', true, 400], [$entry->type->code, $entry->direction->value, $entry->is_settled, (int) $entry->payment_voucher_id]);
        $tax = OwnerTransaction::with('type')->findOrFail(2002);
        $this->assertSame([null, 2000, 'comp_tax'], [$tax->owner_id, (int) $tax->parent_id, $tax->type->code]);

        // staff become admins; numbering continues after imported documents
        $this->assertTrue(User::findOrFail(1)->hasRole('admin'));
        $this->assertSame('RV-'.now()->year.'-00301', app(DocumentNumberGenerator::class)->next('receipt_voucher'));
    }

    public function test_problems_are_reported_as_warnings_not_failures(): void
    {
        $this->import()
            ->expectsOutputToContain('duplicate email admin@legacy.test cleared')
            ->expectsOutputToContain('transaction type unknown_x was not defined')
            ->expectsOutputToContain('payments #1002 skipped')
            ->doesntExpectOutputToContain('SQLSTATE')
            ->doesntExpectOutputToContain('Undefined')
            ->assertSuccessful();

        tenancy()->initialize($this->tenant);
        $this->assertNull(User::findOrFail(7)->email);
        $this->assertFalse(Installment::whereKey(1002)->exists());
    }

    public function test_legacy_md5_password_is_upgraded_on_first_login_by_username(): void
    {
        $this->import()->assertSuccessful();
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel('admin');

        $this->assertFalse(Auth::validate(['username' => 'admin', 'password' => 'wrong']));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user = User::findOrFail(1));
        $user->refresh();
        $this->assertNull($user->legacy_password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_office_print_templates_deeds_plots_and_letters_keep_working(): void
    {
        $this->import(['--legacy-templates' => true])->assertSuccessful();
        tenancy()->initialize($this->tenant);

        $unit = Unit::with(['deeds', 'landPlots', 'meters'])->findOrFail(100);
        $this->assertSame(['4101', 'كتابة عدل الرياض', '1444-03-15'], [$unit->deeds->first()->number, $unit->deeds->first()->source, $unit->deeds->first()->issue_date]);
        $this->assertSame('4101', $unit->deed_number); // first deed mirrored for list search
        $plot = $unit->landPlots->first();
        $this->assertSame(['58', 'شارع (20) م', 'قطعة (59)'], [$plot->number, $plot->describeSide('north', true), $plot->describeSide('east')]);
        $this->assertSame(['private', 'PC-9', 'ACC-7'], [$unit->meters->first()->ownership, $unit->meters->first()->payment_code, $unit->meters->first()->payment_account]);
        $this->assertSame('المملكة العربية السعودية', \App\Models\Owner::find(10)->nationality);

        // the office's own rent contract template replaces the default and prints with legacy names and blocks
        $template = \App\Models\PrintTemplate::where('key', 'rent_contract')->sole();
        $this->assertTrue($template->legacy_syntax);
        $this->assertStringContainsString('<div class="hdr">{org_arname}</div>', $template->header);
        $html = app(\App\Services\Documents\DocumentData::class)->rentContract(\App\Models\RentContract::findOrFail(50));
        $this->assertStringContainsString('<div class="hdr">مكتب الرواد</div>', $html);
        $this->assertStringNotContainsString('sample', $html);
        $this->assertStringContainsString('سعد محمد العتيبي', $html);
        $this->assertStringContainsString('المملكة العربية السعودية', $html);
        foreach (['MTR-1', 'خاص', '10234', 'ACC-7', 'PC-9', '4101', 'كتابة عدل الرياض', '2,500.00', 'ألفان وخمسمئة', '2026-01-01', '2026-12-31'] as $expected) {
            $this->assertStringContainsString($expected, $html, $expected);
        }

        // legacy review letter prints with the plot boundaries
        $letter = \App\Models\Letter::findOrFail(5);
        $this->assertSame(\App\Enums\LetterType::Evaluation, $letter->type);
        $this->assertSame(5, $letter->data['review']['city_id']);
        $this->assertStringContainsString('شارع (20) م', app(\App\Services\Documents\DocumentData::class)->letter($letter));
    }

    public function test_old_templates_are_kept_as_copies_by_default(): void
    {
        $this->import()->assertSuccessful();
        tenancy()->initialize($this->tenant);

        $this->assertFalse(\App\Models\PrintTemplate::where('key', 'rent_contract')->sole()->legacy_syntax);
        $this->assertTrue(\App\Models\PrintTemplate::where('key', 'legacy_rent_contract')->sole()->legacy_syntax);
    }

    public function test_import_refuses_an_office_that_already_has_units_unless_forced(): void
    {
        $this->import()->assertSuccessful();
        $this->import()->expectsOutputToContain('--force')->assertFailed();
    }

    private function seedLegacy(): void
    {
        $this->table('setting', [
            ['varname' => 'sitename', 'value' => 'مكتب الرواد'], ['varname' => 'vat', 'value' => '15'],
            ['varname' => 'system_calendar', 'value' => '2'], ['varname' => 'comm_tax_status', 'value' => '1'],
        ]);
        $this->table('city', [['id' => 5, 'name' => 'الرياض']]);
        $this->table('meter_types', [['id' => 1, 'title' => 'كهرباء']]);
        $this->table('deed_types', [['id' => 1, 'title' => 'صك إلكتروني']]);
        $this->table('country', [['id' => 191, 'name' => 'المملكة العربية السعودية']]);
        $this->table('building_borders', [['id' => 1, 'border' => 'street', 'attrs' => 'LW', 'title' => 'شارع'], ['id' => 6, 'border' => 'build', 'attrs' => 'NL', 'title' => 'قطعة']]);
        $this->table('transaction_types', [['id' => 'payment', 'type' => 'دفعة'], ['id' => 'comp_tax', 'type' => 'ضريبة الشركة']]);
        $this->table('user', [
            ['id' => 1, 'groupid' => 1, 'name' => 'المدير', 'username' => 'admin', 'email' => 'admin@legacy.test', 'password' => md5('secret123'), 'super' => 1, 'active' => 1],
            ['id' => 2, 'groupid' => 2, 'name' => 'سعد', 'username' => 'saad', 'email' => 'saad@legacy.test', 'password' => md5('x'), 'super' => 0, 'active' => 1],
            ['id' => 7, 'groupid' => 3, 'name' => 'خالد', 'username' => 'khaled', 'email' => 'admin@legacy.test', 'password' => md5('y'), 'super' => 0, 'active' => 1],
        ]);
        $this->table('owner', [
            ['id' => 10, 'userid' => 2, 'fname' => 'سعد', 'fathname' => 'محمد', 'lname' => null, 'famname' => 'العتيبي', 'mobile' => '0500000001', 'nationality' => '191'],
            ['id' => 11, 'userid' => 0, 'fname' => 'نورة', 'fathname' => null, 'lname' => null, 'famname' => 'القحطاني', 'mobile' => '0500000002', 'nationality' => ''],
        ]);
        $this->table('buyer', [['id' => 20, 'userid' => 7, 'fname' => 'خالد', 'fathname' => '', 'lname' => '', 'famname' => 'الحربي', 'calendar' => 1]]);
        $this->table('building_locs', [['id' => 3, 'title' => 'برج الرواد', 'citytid' => 5]]);
        $this->table('building', [
            ['id' => 100, 'locid' => 3, 'title' => 'شقة 101', 'type' => 'rent', 'available' => 0, 'contractid' => 50, 'rentvalue' => 30000, 'payment' => 'month',
                'owner' => json_encode(['id' => ['10', '11'], 'share' => ['60', '40'], 'outgoings' => ['60', '40']]), 'meters' => json_encode(['typeid' => ['1'], 'owner_type' => ['private'], 'no' => ['MTR-1'], 'pay_code' => ['PC-9'], 'pay_no' => ['ACC-7']]),
                'deed' => json_encode(['typeid' => ['1'], 'no' => ['4101'], 'source' => ['كتابة عدل الرياض'], 'date' => ['1444-03-15']]),
                'plots' => json_encode(['no' => ['58'], 'size' => ['625'], 'north_type' => ['street'], 'north_width' => ['20'], 'north_length' => ['25'], 'north_plotno' => [''],
                    'east_type' => ['build'], 'east_plotno' => ['59'], 'east_length' => ['25'], 'east_width' => [''], 'south_type' => [''], 'south_plotno' => [''], 'south_length' => [''], 'south_width' => [''],
                    'west_type' => [''], 'west_plotno' => [''], 'west_length' => [''], 'west_width' => ['']]),
                'gps_location' => '24.7,46.6', 'calendar' => 1],
            ['id' => 101, 'locid' => 3, 'title' => 'فيلا 7', 'type' => 'sell', 'available' => 1, 'contractid' => 0, 'rentvalue' => null, 'payment' => null,
                'owner' => json_encode(['id' => ['10'], 'share' => ['100']]), 'meters' => null, 'deed' => null, 'plots' => null, 'gps_location' => null, 'calendar' => 1],
        ]);
        $this->table('contract_rights', [['id' => 1, 'title' => 'الصيانة', 'content' => 'على المستأجر'], ['id' => 2, 'title' => 'التجديد', 'content' => 'بموافقة الطرفين']]);
        $this->table('rent_contracts', [[
            'id' => 50, 'buildid' => 100, 'buyer' => json_encode(['id' => ['20'], 'share' => ['100']]), 'rightsid' => '1,2', 'postdate' => '2026-01-01',
            'startdate' => '2026-01-01', 'enddate' => '2026-12-31', 'period' => 'year', 'periodnum' => 1, 'payment' => 'month', 'rentvalue' => 2500,
            'commission' => 0, 'tax' => 0, 'tax_percent' => 0, 'calendar' => 1, 'ended' => 0, 'archive' => 0, 'meters' => json_encode(['MTR-1' => '10234']),
        ]]);
        $this->table('sell_contracts', [[
            'id' => 60, 'buildid' => 101, 'buyer' => json_encode(['id' => ['20']]), 'rightsid' => null, 'postdate' => '2026-02-01', 'total' => 500000,
            'paytype' => 'once', 'once' => json_encode(['comission' => '2', 'comissiontype' => 'percent', 'comissionmethod' => 'buyer']), 'calendar' => 1, 'archive' => 0,
        ]]);
        $this->table('statement_in', [['id' => 300, 'from_type' => 'buildrenter', 'from_id' => '20', 'buildid' => 100, 'amount' => 2500, 'pay_method' => 'cash', 'issueddate' => '2026-01-01', 'calendar' => 1]]);
        $this->table('statement_out', [['id' => 400, 'from_type' => 'owner', 'from_id' => '10', 'buildid' => 100, 'amount' => 1500, 'pay_method' => 'cash', 'issueddate' => '2026-01-05', 'calendar' => 1]]);
        $this->table('payments', [
            ['id' => 1000, 'buildid' => 100, 'contractid' => 50, 'module' => 'rent', 'type' => 'year_rent_month', 'paydate' => '2026-01-01', 'amount' => 2500, 'gone' => 1, 'statementid' => 300, 'alert' => 0, 'calendar' => 1],
            ['id' => 1001, 'buildid' => 100, 'contractid' => 50, 'module' => 'rent', 'type' => 'year_rent_month', 'paydate' => '2026-02-01', 'amount' => 2500, 'gone' => 0, 'statementid' => 0, 'alert' => 1, 'calendar' => 1],
            ['id' => 1002, 'buildid' => 100, 'contractid' => 999, 'module' => 'rent', 'type' => 'year_rent_month', 'paydate' => '2026-03-01', 'amount' => 2500, 'gone' => 0, 'statementid' => 0, 'alert' => 0, 'calendar' => 1],
        ]);
        $this->table('transactions', [
            ['id' => 2000, 'ownerid' => 10, 'buildid' => 100, 'typeid' => 'payment', 'paymentid' => 1000, 'module' => 'rent', 'contractid' => 50, 'direction' => 'debit', 'amount' => 1500, 'paydate' => '2026-01-01', 'gone' => 1, 'statoutid' => 400, 'parent' => 0],
            ['id' => 2001, 'ownerid' => 11, 'buildid' => 100, 'typeid' => 'payment', 'paymentid' => 1000, 'module' => 'rent', 'contractid' => 50, 'direction' => 'debit', 'amount' => 1000, 'paydate' => '2026-01-01', 'gone' => 0, 'statoutid' => 0, 'parent' => 0],
            ['id' => 2002, 'ownerid' => 0, 'buildid' => 100, 'typeid' => 'comp_tax', 'paymentid' => 1000, 'module' => 'rent', 'contractid' => 50, 'direction' => 'debit', 'amount' => 20, 'paydate' => '2026-01-01', 'gone' => 0, 'statoutid' => 0, 'parent' => 2000],
            ['id' => 2003, 'ownerid' => 10, 'buildid' => 100, 'typeid' => 'unknown_x', 'paymentid' => 0, 'module' => 'build', 'contractid' => 0, 'direction' => 'credit', 'amount' => 5, 'paydate' => '2026-01-01', 'gone' => 0, 'statoutid' => 0, 'parent' => 0],
        ]);
        $this->table('cp_module', [['id' => 5, 'name' => 'building']]);
        // stored HTML-encoded, as the legacy editor saved it; #1/#2 are the shared header/footer
        $this->table('print_samples', [
            ['id' => 1, 'title' => 'رأس الصفحة', 'content' => htmlspecialchars('<div class="hdr">{org_arname}</div>'), 'header' => '0', 'footer' => '0'],
            ['id' => 2, 'title' => 'ذيل الصفحة', 'content' => htmlspecialchars('<div class="ftr">FOOTER</div>'), 'header' => '0', 'footer' => '0'],
            ['id' => 8, 'title' => 'طباعة عقد إيجار', 'header' => '1', 'footer' => '0', 'content' => htmlspecialchars(
                '<p>يوم {day} {date}</p><table class="o"><!--{owners}--><tr><td>sample</td></tr><!--{owners}--></table>'
                .'<table class="m"><!--{meters}--><tr><td>sample</td></tr><!--{meters}--></table><table class="d"><!--{deeds}--><tr><td>sample</td></tr><!--{deeds}--></table>'
                .'<p>الإيجار {total_rent} ({total_rent_text}) من {startdate} إلى {enddate} — {buyers_unused}</p>')],
        ]);
        $this->table('letters', [['id' => 5, 'type' => 'review', 'renterid' => 0, 'buildid' => 0, 'official' => null, 'cancel' => null, 'rentraise' => null, 'calendar' => 1, 'postdate' => '2026-03-01',
            'review' => json_encode(['provider' => 'سعد', 'provider_title' => 'owner', 'city' => '5', 'amount' => '900000',
                'plot' => ['north_type' => 'street', 'north_width' => '20', 'north_length' => '25', 'east_type' => 'build', 'east_plotno' => '59', 'east_length' => '25']])]]);
        $this->table('attachments', [['id' => 1, 'moduleid' => 5, 'objectid' => 100, 'path' => 'building/front.jpg', 'name' => 'الواجهة']]);
    }

    /** Creates a loosely typed legacy table from the row keys and fills it. */
    private function table(string $name, array $rows): void
    {
        $columns = array_keys(array_merge(...$rows));
        $numericIds = is_int($rows[0]['id'] ?? null);
        DB::connection('legacy')->getSchemaBuilder()->create($name, function (Blueprint $table) use ($columns, $numericIds) {
            foreach ($columns as $column) {
                match (true) {
                    $column !== 'id' => $table->text($column)->nullable(),
                    $numericIds => $table->integer('id')->primary(),
                    default => $table->string('id')->primary(),
                };
            }
        });
        DB::connection('legacy')->table($name)->insert($rows);
    }
}
