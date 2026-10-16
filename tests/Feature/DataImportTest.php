<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\UnitStatus;
use App\Filament\Admin\Pages\DataImport as DataImportPage;
use App\Models\Building;
use App\Models\Client;
use App\Models\OutgoingMessage;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\Import\DataImport;
use App\Services\Import\Spreadsheet;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Moving an office's records over from Excel: templates, row-by-row preview, idempotent import. */
class DataImportTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-11-10 09:00:00');
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_owners_are_imported_once_and_updated_only_when_asked(): void
    {
        $file = $this->xlsx('owners', [
            ['سعد', 'محمد', '', 'العتيبي', 'هوية وطنية', '١٠١٢٣٤٥٦٧٨', 'سعودي', '1980-05-14', '966501234567', '', '', ''],
            ['نورة', '', '', 'القحطاني', '', '1022222222', '', '14/05/1985', '501112222', 'noura@example.com', '', ''],
            ['', '', '', 'بلا اسم', '', '', '', 'تاريخ خطأ', '', 'not-an-email', '', ''],
        ]);

        $preview = app(DataImport::class)->preview('owners', $file, 'xlsx');
        $this->assertSame(3, $preview['rows']);
        $this->assertSame(2, $preview['ready']);
        $this->assertSame(4, $preview['problems'][0]['row']);
        $this->assertCount(3, $preview['problems'][0]['problems'], 'missing name, bad date, bad e-mail');

        $result = app(DataImport::class)->run('owners', $file, 'xlsx');
        $this->assertSame([2, 0, 0, 1], [$result['created'], $result['updated'], $result['skipped'], count($result['failed'])]);
        $saad = Owner::where('id_number', '1012345678')->sole();
        $this->assertSame('0501234567', $saad->mobile, 'Arabic-Indic digits and 966 normalised');
        $this->assertSame('national_id', $saad->id_type->value);
        $this->assertSame('1985-05-14', Owner::where('mobile', '0501112222')->sole()->birth_date->format('Y-m-d'));

        $again = app(DataImport::class)->run('owners', $file, 'xlsx');
        $this->assertSame([0, 2], [$again['created'], $again['skipped']], 'the same file twice does not duplicate');
        $this->assertSame(2, Owner::count());

        $updated = app(DataImport::class)->run('owners', $file, 'xlsx', ['update_existing' => true]);
        $this->assertSame(2, $updated['updated']);
    }

    public function test_clients_from_csv_with_companies(): void
    {
        $csv = "\u{FEFF}الاسم الأول *,اسم العائلة,رقم الهوية,الجوال,نوع العميل,اسم المنشأة,الرقم الضريبي\n"
            ."خالد,الحربي,1098765432,0559876543,فرد,,\n"
            ."عبدالرحمن,السبيعي,,0551231234,,شركة البناء المتقدم,300012345600003\n";
        $file = $this->path('csv');
        file_put_contents($file, $csv);

        $result = app(DataImport::class)->run('clients', $file, 'csv');
        $this->assertSame(2, $result['created']);
        $company = Client::where('mobile', '0551231234')->sole();
        $this->assertSame('company', $company->kind->value);
        $this->assertSame('300012345600003', $company->vat_number);
        $this->assertSame('individual', Client::where('id_number', '1098765432')->sole()->kind->value);
    }

    public function test_units_create_their_building_and_location_and_link_the_owner(): void
    {
        $owner = Owner::create(['first_name' => 'سعد', 'family_name' => 'العتيبي', 'id_number' => '1012345678']);
        $file = $this->xlsx('units', [
            ['شقة 12', 'عمارة النخيل', 'الرياض', 'العليا', '12', '3', '4', '140', 'للإيجار', '36,000', 'شهري', '310105012345', '1012345678', '', '100'],
            ['شقة 14', 'عمارة النخيل', 'الرياض', 'العليا', '14', '3', '3', '120', '', '30000', 'ربع سنوي', '', '', 'فهد ناصر الدوسري', '50'],
            ['محل 1', '', '', '', '', '', '', 'كبير', 'للتأجير', '', 'كل شهر', '', '1099999999', '', '150'],
        ]);

        $preview = app(DataImport::class)->preview('units', $file, 'xlsx');
        $this->assertSame(2, $preview['ready']);
        $this->assertCount(5, $preview['problems'][0]['problems'], 'area, offer type, cycle, share > 100, unknown owner');

        $result = app(DataImport::class)->run('units', $file, 'xlsx');
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, Building::count());
        $unit = Unit::where('title', 'شقة 12')->sole();
        $this->assertSame('عمارة النخيل', $unit->building->title);
        $this->assertSame('العليا', $unit->district->name);
        $this->assertSame('الرياض', $unit->city->name);
        $this->assertSame(36000.0, (float) $unit->rent_amount);
        $this->assertSame('month', $unit->rent_payment_cycle->value);
        $this->assertSame([$owner->id], $unit->owners->modelKeys());

        $fahd = Owner::where('full_name', 'فهد ناصر الدوسري')->sole();
        $this->assertSame('ناصر', $fahd->father_name);
        $this->assertSame(50.0, (float) Unit::where('title', 'شقة 14')->sole()->owners->first()->pivot->share_percent);

        $this->assertSame(2, app(DataImport::class)->run('units', $file, 'xlsx')['skipped']);
        $this->assertSame(2, Unit::count());
    }

    public function test_running_contracts_get_their_schedule_with_past_payments_closed_and_nobody_is_messaged(): void
    {
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 12']);
        $this->makeUnit([[100, 100]], ['title' => 'شقة 14']);
        $file = $this->xlsx('rent_contracts', [
            ['شقة 12', '', '1098765432', '0559876543', 'خالد سعد الحربي', '2026-01-01', '2026-12-31', '3000', 'شهري', '1500', '20154012345', ''],
            ['شقة 14', '', '', '0551111111', '', '2026-01-01', '2026-12-31', '3000', 'شهري', '', '', ''], // unknown tenant, no name
            ['شقة 14', '', '', '0552222222', 'سارة', '2025-01-01', '2025-12-31', '3000', 'شهري', '', '', ''], // ended
            ['شقة 14', '', '', '0552222222', 'سارة', '2026-03-01', '2027-03-31', '9000', 'ربع سنوي', '', '', ''], // 13 months, quarterly
            ['شقة 99', '', '', '0552222222', 'سارة', '2026-03-01', '2027-02-28', '9000', 'ربع سنوي', '', '', ''],
        ]);

        $preview = app(DataImport::class)->preview('rent_contracts', $file, 'xlsx');
        $this->assertSame(1, $preview['ready']);
        $this->assertSame([3, 4, 5, 6], array_column($preview['problems'], 'row'));

        $result = app(DataImport::class)->run('rent_contracts', $file, 'xlsx', ['collect_past' => true]);
        $this->assertSame(1, $result['created']);

        $contract = RentContract::sole();
        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertSame(['year', 1, 'month'], [$contract->period_unit->value, $contract->period_count, $contract->payment_cycle->value]);
        $this->assertSame(36000.0, (float) $contract->yearly_rent);
        $this->assertSame('documented', $contract->ejar_status->value);
        $this->assertSame('خالد سعد الحربي', $contract->clients->sole()->full_name);
        $this->assertSame(12, $contract->installments()->count());
        $this->assertSame(11, $contract->installments()->where('is_collected', true)->count(), 'January to November 1st');
        $this->assertSame('2026-12-01', $contract->fresh()->next_payment_date->format('Y-m-d'));
        $this->assertSame(UnitStatus::Rented, $unit->fresh()->status);
        $this->assertSame(0, OutgoingMessage::count(), 'imported contracts are not announced');

        $this->assertSame(1, app(DataImport::class)->run('rent_contracts', $file, 'xlsx')['skipped']);
        $this->assertSame(1, RentContract::count());
    }

    public function test_a_hijri_contract_keeps_the_hijri_calendar(): void
    {
        $this->makeUnit([[100, 100]], ['title' => 'شقة 12']);
        $file = $this->xlsx('rent_contracts', [
            ['شقة 12', '', '', '0559876543', 'خالد الحربي', '1448-01-01', '1448-12-29', '12000', 'ربع سنوي', '', '', ''],
        ]);
        $preview = app(DataImport::class)->preview('rent_contracts', $file, 'xlsx');
        $this->assertSame([], $preview['problems']);

        app(DataImport::class)->run('rent_contracts', $file, 'xlsx');
        $contract = RentContract::sole();
        $this->assertSame('hijri', $contract->calendar->value);
        $this->assertSame(['year', 1], [$contract->period_unit->value, $contract->period_count]);
        $this->assertSame(4, $contract->installments()->count());
    }

    public function test_the_page_downloads_templates_previews_and_imports(): void
    {
        Date::setTestNow(); // Livewire's temporary uploads are checked against the real clock
        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $template = $this->path('xlsx');
        copy(DataImport::template('clients'), $template);
        $rows = Spreadsheet::rows($template, 'xlsx');
        $this->assertSame('الاسم الأول *', array_key_first($rows[0]));

        $csv = "الاسم الأول,اسم العائلة,الجوال\nسعد,العتيبي,0501234567\n,بلا اسم,0500000000\n";
        Livewire::test(DataImportPage::class)
            ->assertOk()->assertSee(__('الملاك'))->assertSee(__('عقود الإيجار السارية'))
            ->callAction(TestAction::make('template')->arguments(['type' => 'units']))->assertFileDownloaded()
            ->call('choose', 'owners')
            ->fillForm(['upload' => UploadedFile::fake()->createWithContent('owners.csv', $csv)])
            ->call('check')
            ->assertHasNoErrors()
            ->assertSet('preview.ready', 1)
            ->assertSee(__('استيراد :n صفًا', ['n' => 1]))
            ->call('runImport')
            ->assertSet('result.created', 1)
            ->assertSet('file', null);

        $this->assertSame('0501234567', Owner::sole()->mobile);
    }

    public function test_staff_without_settings_access_cannot_open_it(): void
    {
        $clerk = User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']);
        $this->actingAs($clerk);
        Filament::setCurrentPanel('admin');

        $this->assertFalse(DataImportPage::canAccess());
    }

    /** @param list<list<string>> $rows */
    private function xlsx(string $type, array $rows): string
    {
        $path = $this->path('xlsx');
        $importer = DataImport::importer($type);
        Spreadsheet::template($path, $importer::headers(), $rows);

        return $path;
    }

    private function path(string $extension): string
    {
        return $this->files[] = tempnam(sys_get_temp_dir(), 'imp').'.'.$extension;
    }
}
