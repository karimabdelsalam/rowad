<?php

namespace Tests\Feature;

use App\Enums\EjarStatus;
use App\Filament\Admin\Resources\RentContracts\Pages\ListRentContracts;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Models\Client;
use App\Models\Lookups\MeterType;
use App\Models\RentContract;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Ejar\EjarSheet;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Ejar: the data sheet staff copy into the platform, its readiness checklist and the documentation record. */
class EjarTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private RentContract $contract;

    private Client $renter;

    protected function setUp(): void
    {
        parent::setUp();
        $office = $this->initializeTenancy();
        $this->host = 'http://'.$office->domains()->value('domain');
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 4', 'number' => '4', 'area' => 140, 'deed_number' => '310105040123']);
        $unit->owners()->first()->update(['id_type' => 'national_id', 'id_number' => '1012345678', 'birth_date' => '1975-03-02', 'mobile' => '0501112222']);
        $this->renter = Client::create(['first_name' => 'سعد', 'family_name' => 'المطيري', 'id_type' => 'iqama', 'id_number' => '2412345678', 'mobile' => '0553334444']);
        $this->contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter', 'rent_amount' => 9000,
        ], [$this->renter->id => 100]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function complete(): void
    {
        Settings::set('commercial_register', '1010234567', 'identity');
        Settings::set('fal_license_number', '1100012345', 'identity');
        Settings::flush();
        $unit = $this->contract->unit;
        $unit->update(['city_id' => \App\Models\City::create(['name' => 'الرياض'])->id, 'district_id' => \App\Models\District::create(['name' => 'العليا'])->id]);
        $unit->meters()->create(['meter_type_id' => MeterType::firstOrCreate(['title' => 'كهرباء'])->id, 'number' => '4100230011']);
        $this->renter->update(['birth_date' => '1990-07-15']);
    }

    public function test_the_sheet_lists_what_ejar_needs_and_flags_the_gaps(): void
    {
        $missing = EjarSheet::missing($this->contract);

        $this->assertContains(__('مكتب الوساطة').': '.__('رقم رخصة فال (الوساطة العقارية)'), $missing);
        $this->assertContains(__('المستأجر').': '.__('تاريخ الميلاد'), $missing);
        $this->assertContains(__('العقار والوحدة').': '.__('رقم عداد الكهرباء'), $missing);
        $this->assertNotContains(__('المؤجر').': '.__('تاريخ الميلاد'), $missing, 'the owner has one');
        $this->assertLessThan(100, EjarSheet::readiness($this->contract));

        $values = collect(EjarSheet::for($this->contract))->flatMap(fn ($g) => collect($g['rows'])->pluck('value', 'label'));
        $this->assertSame('310105040123', $values[__('رقم الصك')]);
        $this->assertSame('36,000.00', $values[__('الإيجار السنوي')], 'quarterly 9,000 = 36,000 a year');
        $this->assertSame('1975-03-02', collect(EjarSheet::for($this->contract)[1]['rows'])->firstWhere('label', __('تاريخ الميلاد'))['value']);

        $this->complete();
        $this->contract->refresh()->unsetRelations();
        $this->assertSame([], EjarSheet::missing($this->contract));
        $this->assertSame(100, EjarSheet::readiness($this->contract));
    }

    public function test_a_company_tenant_is_described_by_its_registration(): void
    {
        $this->renter->update(['kind' => 'company', 'company_name' => 'شركة الأفق', 'company_registration_number' => '1010456789']);

        $tenant = collect(EjarSheet::for($this->contract->refresh()))->firstWhere('title', __('المستأجر'));
        $this->assertSame('1010456789', collect($tenant['rows'])->firstWhere('label', __('رقم السجل التجاري'))['value']);
        $this->assertNull(collect($tenant['rows'])->firstWhere('label', __('تاريخ الميلاد')));
    }

    public function test_staff_record_the_documentation_and_the_tenant_sees_it(): void
    {
        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $this->assertSame(EjarStatus::Pending, $this->contract->fresh()->ejar_status);

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])
            ->assertOk()
            ->assertSee(__('منصة إيجار'))
            ->assertSee(__('جاهزية البيانات :p%', ['p' => EjarSheet::readiness($this->contract)]))
            ->assertActionVisible('ejarSheet');
        $sheet = view('filament.admin.ejar-sheet', ['groups' => EjarSheet::for($this->contract), 'missing' => EjarSheet::missing($this->contract)])->render();
        $this->assertStringContainsString('310105040123', $sheet);
        $this->assertStringContainsString(__('ناقص'), $sheet);

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])
            ->callAction('ejarRecord', ['ejar_status' => 'documented', 'ejar_number' => ''])
            ->assertHasActionErrors(['ejar_number' => 'required']);

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])
            ->callAction('ejarRecord', ['ejar_status' => 'documented', 'ejar_number' => '20154241355', 'ejar_documented_at' => '2025-12-30'])
            ->assertHasNoActionErrors();

        $contract = $this->contract->fresh();
        $this->assertSame(EjarStatus::Documented, $contract->ejar_status);
        $this->assertSame('20154241355', $contract->ejar_number);
        $this->assertSame('2025-12-30', $contract->ejar_documented_at->toDateString());

        Livewire::test(ListRentContracts::class)
            ->filterTable('ejar_status', 'pending')->assertCanNotSeeTableRecords([$contract])
            ->filterTable('ejar_status', 'documented')->assertCanSeeTableRecords([$contract]);

        $pdf = $contract->addMediaFromString("%PDF-1.4\n%ejar\n")->usingFileName('ejar.pdf')->toMediaCollection('ejar');
        $user = app(PortalAccounts::class)->issue($this->renter, 'saad@portal.test', 'secret-pass-1')['user'];
        $this->flushSession();
        $this->actingAs($user);
        Filament::setCurrentPanel('client');
        Livewire::test(\App\Filament\Client\Resources\RentContracts\Pages\ManageRentContracts::class)->assertCanSeeTableRecords([$contract]);
        $this->get("{$this->host}/media/{$pdf->id}")->assertOk();

        $stranger = app(PortalAccounts::class)->issue($this->makeClient(), 'other@portal.test', 'secret-pass-1')['user'];
        $this->flushSession();
        $this->actingAs($stranger);
        $this->get("{$this->host}/media/{$pdf->id}")->assertNotFound();
    }

    public function test_a_contract_can_be_marked_as_not_needing_ejar(): void
    {
        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])
            ->callAction('ejarNotRequired', ['ejar_notes' => 'عقد سكن عمال داخلي'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('ejarNotRequired');

        $this->assertSame(EjarStatus::NotRequired, $this->contract->fresh()->ejar_status);
    }
}
