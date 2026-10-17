<?php

namespace Tests\Feature;

use App\Enums\EjarStatus;
use App\Filament\Admin\Pages\EjarIntegration;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Admin\Resources\Units\Pages\ViewUnit;
use App\Models\Client;
use App\Models\Lookups\MeterType;
use App\Models\RentContract;
use App\Models\Setting;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Ejar\EjarException;
use App\Services\Ejar\EjarRegistration;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Ejar ECRS API: each office connects its own key, links units to Ejar properties and sends contracts for registration. */
class EjarApiTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private const BASE = 'https://ejar.sa/api/v1/ecrs';

    private string $host;

    private RentContract $contract;

    private Client $renter;

    /** @var list<string> "METHOD path" of every call Ejar received */
    private array $calls = [];

    /** @var array<string, \Closure|array> "METHOD path" => response */
    private array $routes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = 'http://'.$this->initializeTenancy()->domains()->value('domain');
        Settings::set('commercial_register', '1010234567', 'identity');
        Settings::set('fal_license_number', '1100012345', 'identity');

        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 4', 'number' => '4', 'area' => 140, 'deed_number' => '310105040123',
            'city_id' => \App\Models\City::create(['name' => 'الرياض'])->id, 'district_id' => \App\Models\District::create(['name' => 'العليا'])->id]);
        $unit->owners()->first()->update(['id_type' => 'national_id', 'id_number' => '1012345678', 'birth_date' => '1975-03-02', 'mobile' => '0501112222']);
        $unit->meters()->create(['meter_type_id' => MeterType::firstOrCreate(['title' => 'كهرباء'])->id, 'number' => '4100230011']);
        $unit->meters()->create(['meter_type_id' => MeterType::firstOrCreate(['title' => 'مياه'])->id, 'number' => 'W-77']);
        $this->renter = Client::create(['first_name' => 'سعد', 'family_name' => 'المطيري', 'id_type' => 'iqama', 'id_number' => '2412345678',
            'birth_date' => '1990-07-15', 'mobile' => '0553334444']);
        $this->contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => today()->toDateString(), 'start_date' => today()->addDays(3)->toDateString(),
            'end_date' => today()->addDays(3)->addYear()->subDay()->toDateString(), 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter',
            'rent_amount' => 9000, 'meter_readings' => ['4100230011' => '1520'],
        ], [$this->renter->id => 100]);

        $this->actingAs(User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('admin');

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/api/v1/ecrs'));
            $key = $request->method().' '.$path;
            $this->calls[] = $key;
            $response = $this->routes[$key] ?? ['status' => 'error'];
            $response = $response instanceof \Closure ? $response($request) : $response;

            return $response instanceof \GuzzleHttp\Promise\PromiseInterface ? $response : Http::response($response, isset($response['errors']) ? 422 : 200);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function connect(): void
    {
        Settings::set('ejar_enabled', '1', 'ejar');
        Settings::set('ejar_api_key', 'office-key', 'ejar');
        Settings::set('ejar_api_secret', 'office-secret', 'ejar');
    }

    private function ejarHappyPath(): void
    {
        $this->routes = [
            'POST /contracts' => ['status' => 200, 'result' => 'success', 'data' => ['contract' => ['id' => 'c-901', 'contract_number' => '20154241355', 'version' => 1]]],
            'POST /contracts/c-901/units' => ['result' => 'success', 'data' => ['id' => 'c-901', 'relationships' => ['contract_units' => [['id' => 'cu-55']]]]],
            'POST /contracts/c-901/party' => ['result' => 'success', 'data' => []],
            'POST /contracts/c-901/unit_services' => ['result' => 'success', 'data' => []],
            'POST /contracts/c-901/submit' => ['result' => 'success', 'data' => []],
        ];
    }

    public function test_an_office_connects_its_own_key_which_is_checked_and_stored_encrypted(): void
    {
        $this->routes['GET /contracts'] = fn () => Http::response(['errors' => [['code' => 'E401', 'detail' => ['ar' => 'غير مصرح']]]], 401);
        Livewire::test(EjarIntegration::class)
            ->callAction('connect', ['ejar_api_key' => 'wrong', 'ejar_api_secret' => 'bad'])
            ->assertNotified(__('تعذّر الربط مع إيجار'));
        $this->assertFalse(\App\Services\Ejar\EjarApi::connected());

        $this->routes['GET /contracts'] = function (Request $request) {
            $this->assertSame('Basic '.base64_encode('office-key:office-secret'), $request->header('Authorization')[0]);

            return ['status' => 200, 'result' => 'success', 'data' => []];
        };
        Livewire::test(EjarIntegration::class)
            ->callAction('connect', ['ejar_api_key' => 'office-key', 'ejar_api_secret' => 'office-secret'])
            ->assertNotified(__('تم ربط المكتب بمنصة إيجار'));

        $this->assertTrue(\App\Services\Ejar\EjarApi::connected());
        $this->assertSame('office-secret', Settings::get('ejar_api_secret'));
        $this->assertNotSame('office-secret', Setting::where('key', 'ejar_api_secret')->value('value'), 'encrypted at rest');
        $this->get("{$this->host}/admin/ejar")->assertOk()->assertSee(__('مرتبط بمنصة إيجار'));

        Livewire::test(EjarIntegration::class)->callAction('disconnect');
        $this->assertFalse(\App\Services\Ejar\EjarApi::connected());
    }

    public function test_a_unit_is_linked_to_its_ejar_property_by_deed(): void
    {
        $this->connect();
        $this->routes['GET /properties'] = function (Request $request) {
            $this->assertSame(['document_number' => '310105040123', 'issued_date' => '2019-05-12'], $request->data()['data']);

            return ['result' => 'success', 'data' => [['type' => 'portfolio_properties', 'attributes' => ['id' => 'p-1', 'property_name' => 'برج النخيل', 'property_number' => '7']]]];
        };
        $this->routes['GET /properties/p-1/units'] = ['result' => 'success', 'data' => [
            ['id' => 'u-3', 'attributes' => ['id' => 'u-3', 'unit_number' => '3', 'unit_type' => 'apartment']],
            ['id' => 'u-4', 'attributes' => ['id' => 'u-4', 'unit_number' => '4', 'unit_type' => 'apartment']],
        ]];

        Livewire::test(ViewUnit::class, ['record' => $this->contract->unit_id])
            ->mountAction('ejarLink')
            ->setActionData(['document_number' => '310105040123', 'issued_date' => '2019-05-12', 'ejar_property_id' => 'p-1', 'ejar_unit_id' => 'u-4'])
            ->callMountedAction()->assertHasNoActionErrors();

        $unit = $this->contract->unit->fresh();
        $this->assertSame(['p-1', 'u-4'], [$unit->ejar_property_id, $unit->ejar_unit_id]);
    }

    public function test_a_contract_is_registered_on_ejar_step_by_step(): void
    {
        $this->assertContains(__('اربط حساب المكتب بمنصة إيجار من صفحة "منصة إيجار" في الإعدادات.'), EjarRegistration::problems($this->contract));
        $this->connect();
        $this->assertContains(__('اربط الوحدة بعقارها في إيجار أولًا (زر "ربط بإيجار" في صفحة الوحدة).'), EjarRegistration::problems($this->contract));
        $this->contract->unit->forceFill(['ejar_property_id' => 'p-1', 'ejar_unit_id' => 'u-4'])->save();
        $this->assertSame([], EjarRegistration::problems($this->contract->fresh()));

        $this->ejarHappyPath();
        $sent = [];
        foreach (['party', 'unit_services', 'units'] as $step) {
            $ok = $this->routes["POST /contracts/c-901/{$step}"];
            $this->routes["POST /contracts/c-901/{$step}"] = function (Request $request) use (&$sent, $step, $ok) {
                $sent[$step] = $request->data()['data'];

                return $ok;
            };
        }

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])->callAction('ejarSend')->assertNotified(__('أُرسل العقد إلى إيجار'));

        $contract = $this->contract->fresh();
        $this->assertSame(EjarStatus::AwaitingParties, $contract->ejar_status);
        $this->assertSame(['20154241355', 'c-901', 1], [$contract->ejar_number, $contract->ejar_contract_id, $contract->ejar_version]);
        $this->assertNull($contract->ejar_error);
        $this->assertSame(['POST /contracts', 'POST /contracts/c-901/units', 'POST /contracts/c-901/party', 'POST /contracts/c-901/unit_services', 'POST /contracts/c-901/submit'], $this->calls);

        $this->assertSame(['id' => 'p-1', 'contract_units' => [['id' => 'u-4']]], $sent['units']['contract_property']);
        $this->assertSame([
            ['party_type' => 'individual', 'party_role' => 'lessor', 'party_data' => ['party_id' => '1012345678', 'party_id_type' => 'national_id']],
            ['party_type' => 'individual', 'party_role' => 'tenant', 'party_data' => ['party_id' => '2412345678', 'party_id_type' => 'resident_id']],
        ], $sent['party']['contract_parties']);
        $services = collect($sent['unit_services']['contract_unit_services'])->keyBy('utility_service_type');
        $this->assertSame(['electricity', 'water'], $services->keys()->sort()->values()->all());
        $this->assertSame('cu-55', $services['electricity']['contract_unit_id']);
        $this->assertEquals(1520, $services['electricity']['meter_current_reading']);
        $this->assertSame('metered_fee', $services['water']['to_be_paid_by']);
    }

    public function test_a_rejected_step_is_explained_and_resumed_without_a_second_draft(): void
    {
        $this->connect();
        $this->contract->unit->forceFill(['ejar_property_id' => 'p-1', 'ejar_unit_id' => 'u-4'])->save();
        $this->ejarHappyPath();
        $this->routes['POST /contracts/c-901/party'] = ['errors' => [['status' => 422, 'code' => 'E1023', 'detail' => ['en' => 'Tenant not verified', 'ar' => 'تعذر التحقق من هوية المستأجر']]]];

        try {
            EjarRegistration::make()->register($this->contract);
            $this->fail('the rejection should surface');
        } catch (EjarException $e) {
            $this->assertSame(['تعذر التحقق من هوية المستأجر (E1023)'], $e->messages);
        }
        $contract = $this->contract->fresh();
        $this->assertSame(EjarStatus::Pending, $contract->ejar_status);
        $this->assertStringContainsString('تعذر التحقق من هوية المستأجر', $contract->ejar_error);
        $this->assertSame('parties', EjarRegistration::make()->nextStep($contract->ejar_progress));

        $this->ejarHappyPath();
        $this->calls = [];
        EjarRegistration::make()->register($contract);

        $this->assertSame(['POST /contracts/c-901/party', 'POST /contracts/c-901/unit_services', 'POST /contracts/c-901/submit'], $this->calls, 'continues where it stopped');
        $this->assertSame(EjarStatus::AwaitingParties, $contract->fresh()->ejar_status);
        $this->assertNull($contract->fresh()->ejar_error);
    }

    public function test_the_hourly_sync_marks_registered_contracts_documented_and_keeps_ejars_pdf(): void
    {
        $this->connect();
        $this->contract->forceFill(['ejar_status' => EjarStatus::AwaitingParties, 'ejar_number' => '20154241355', 'ejar_contract_id' => 'c-901', 'ejar_version' => 1])->save();
        $this->routes['GET /contracts'] = ['result' => 'success', 'data' => ['contract' => ['id' => 'c-901', 'state' => 'approval_pending']]];

        $this->artisan('rowad:ejar:sync')->assertSuccessful()->expectsOutputToContain('1 checked, 0 documented');
        $this->assertSame(EjarStatus::AwaitingParties, $this->contract->fresh()->ejar_status);
        $this->assertSame('approval_pending', $this->contract->fresh()->ejar_state);

        $this->routes['GET /contracts'] = ['result' => 'success', 'data' => ['contract' => ['id' => 'c-901', 'state' => 'registered']]];
        $this->routes['GET /contracts/c-901/download_pdf'] = fn () => Http::response('%PDF-1.4 ejar', 200, ['Content-Type' => 'application/pdf']);
        $this->artisan('rowad:ejar:sync')->assertSuccessful()->expectsOutputToContain('1 checked, 1 documented');

        $contract = $this->contract->fresh();
        $this->assertSame(EjarStatus::Documented, $contract->ejar_status);
        $this->assertTrue($contract->ejar_documented_at->isToday());
        $this->assertSame('ejar-20154241355.pdf', $contract->getFirstMedia('ejar')->file_name);
        $this->assertStringContainsString('ejar', $contract->getFirstMedia('ejar')->getPath());
    }

    public function test_an_unsubmitted_draft_can_be_withdrawn(): void
    {
        $this->connect();
        $this->contract->forceFill(['ejar_contract_id' => 'c-901', 'ejar_progress' => ['contract' => now()->toIso8601String()], 'ejar_error' => 'x'])->save();
        $this->routes['DELETE /contracts/c-901'] = ['result' => 'success'];

        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])->callAction('ejarWithdraw');

        $contract = $this->contract->fresh();
        $this->assertSame(['DELETE /contracts/c-901'], $this->calls);
        $this->assertNull($contract->ejar_contract_id);
        $this->assertNull($contract->ejar_progress);
        $this->assertSame('archived', $contract->ejar_state);
    }

    public function test_company_tenants_are_sent_as_organizations(): void
    {
        $this->connect();
        $this->contract->unit->forceFill(['ejar_property_id' => 'p-1', 'ejar_unit_id' => 'u-4'])->save();
        $this->renter->update(['kind' => 'company', 'company_name' => 'شركة المدار', 'company_registration_number' => '1010998877']);
        $this->assertContains(__('أدخل تاريخ قيد السجل التجاري للمستأجر :name.', ['name' => 'شركة المدار']), EjarRegistration::problems($this->contract->fresh()));

        $this->renter->update(['company_registration_date' => '2015-02-10']);
        $this->ejarHappyPath();
        $parties = null;
        $this->routes['POST /contracts/c-901/party'] = function (Request $request) use (&$parties) {
            $parties = $request->data()['data']['contract_parties'];

            return ['result' => 'success', 'data' => []];
        };
        EjarRegistration::make()->register($this->contract->fresh());

        $this->assertSame(['party_type' => 'organization', 'party_role' => 'tenant', 'party_data' => ['cr_number' => '1010998877', 'registration_date' => '2015-02-10']], $parties[1]);
    }
}
