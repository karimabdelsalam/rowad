<?php

namespace Tests\Feature;

use App\Enums\EjarStatus;
use App\Filament\Admin\Resources\Owners\Pages\ViewOwner;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Admin\Resources\Units\Pages\ListUnits;
use App\Filament\Admin\Resources\Units\Pages\ViewUnit;
use App\Models\Building;
use App\Models\Lookups\MeterType;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ejar\EjarPortfolio;
use App\Services\Ejar\EjarRegistration;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Ejar portfolio: registering properties and units from the system, verifying parties, linking existing contracts. */
class EjarPortfolioTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private Building $building;

    private Unit $unit;

    /** @var list<string> */
    private array $calls = [];

    /** @var array<string, array> request bodies by "METHOD path" */
    private array $sent = [];

    /** @var array<string, \Closure|array> */
    private array $routes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Settings::set('ejar_enabled', '1', 'ejar');
        Settings::set('ejar_api_key', 'office-key', 'ejar');
        Settings::set('ejar_api_secret', 'office-secret', 'ejar');

        $city = \App\Models\City::create(['name' => 'الرياض']);
        $this->building = Building::create(['title' => 'برج النخيل', 'number' => '12', 'floors' => 3, 'city_id' => $city->id,
            'district_id' => \App\Models\District::create(['name' => 'العليا', 'city_id' => $city->id])->id, 'street' => 'طريق العليا']);
        $this->unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 4', 'number' => '4', 'floor' => '1', 'rooms' => 3, 'area' => 140,
            'deed_number' => '310105040123', 'building_id' => $this->building->id]);
        $this->unit->deeds()->create(['number' => '310105040123', 'source' => 'كتابة العدل الأولى بالرياض', 'issue_date' => '1440/03/12']);
        $this->unit->meters()->create(['meter_type_id' => MeterType::firstOrCreate(['title' => 'كهرباء'])->id, 'number' => '4100230011']);
        $this->unit->owners()->first()->update(['id_type' => 'national_id', 'id_number' => '1012345678', 'birth_date' => '1975-03-02']);

        $this->actingAs(User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('admin');

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $key = $request->method().' '.substr(parse_url($request->url(), PHP_URL_PATH), strlen('/api/v1/ecrs'));
            $this->calls[] = $key;
            $this->sent[$key] = $request->data();
            $response = $this->routes[$key] ?? ['errors' => [['code' => 'E404', 'detail' => ['ar' => 'غير موجود']]]];
            $response = $response instanceof \Closure ? $response($request) : $response;

            return $response instanceof \GuzzleHttp\Promise\PromiseInterface ? $response : Http::response($response, isset($response['errors']) ? 422 : 200);
        });
        $this->routes = [
            'GET /individual_entities' => ['data' => ['type' => 'individual entity', 'id' => 'ent-1', 'attributes' => ['id' => 'ent-1', 'full_name' => 'مالك 1', 'verification_status' => 'verification_succeed']]],
            'POST /properties' => ['data' => ['type' => 'portfolio_properties', 'attributes' => ['id' => 'p-77', 'property_name' => 'برج النخيل']]],
            'POST /properties/p-77/units' => ['data' => ['type' => 'portfolio_units', 'attributes' => ['id' => 'u-9', 'property_id' => 'p-77']]],
        ];
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function formData(): array
    {
        return [
            'property' => ['ownership_document_type' => 'electronic_title_deed', 'issued_by' => 'وزارة العدل', 'region' => 'al_riyadh', 'building_number' => '1223',
                'postal_code' => '12533', 'additional_number' => '2221', 'units_per_floor' => 2, 'facilities' => ['elevators' => 2, 'parking_spaces' => 10]],
            'unit' => ['unit_direction' => 'north', 'unit_finishing' => 'finished', 'amenities' => ['bedrooms' => 3, 'bathrooms_full' => 2]],
        ];
    }

    public function test_the_form_starts_from_what_the_system_knows(): void
    {
        $property = EjarPortfolio::propertyState($this->unit);
        $this->assertSame(['310105040123', 'برج النخيل', '12', 'building', 'al_riyadh', 'الرياض', 'العليا', 'طريق العليا', 3],
            [$property['document_number'], $property['property_name'], $property['property_number'], $property['property_type'], $property['region'],
                $property['city'], $property['district'], $property['street_name'], $property['total_floors']]);
        $this->assertSame('2018-11-20', $property['issued_date'], 'the Hijri deed date becomes Gregorian');

        $unit = EjarPortfolio::unitState($this->unit);
        $this->assertSame(['4', '1', 'apartment', 140.0, 3, '4100230011'], [$unit['unit_number'], $unit['floor_number'], $unit['unit_type'], $unit['area'], $unit['rooms'], $unit['electricity_meter']]);
    }

    public function test_the_property_and_unit_are_registered_on_ejar_from_the_unit_page(): void
    {
        $sibling = Unit::create(['title' => 'شقة 5', 'number' => '5', 'offer_type' => 'rent', 'building_id' => $this->building->id]);

        Livewire::test(ViewUnit::class, ['record' => $this->unit->id])
            ->mountAction('ejarRegister')
            ->setActionData(array_replace_recursive(['property' => EjarPortfolio::propertyState($this->unit), 'unit' => EjarPortfolio::unitState($this->unit)], $this->formData()))
            ->callMountedAction()->assertHasNoActionErrors()->assertNotified(__('سُجلت الوحدة في إيجار'));

        $this->assertSame(['GET /individual_entities', 'POST /properties', 'POST /properties/p-77/units'], $this->calls);

        $property = $this->sent['POST /properties']['data'];
        $this->assertSame(['310105040123', '2018-11-20', 'electronic_title_deed', 'وزارة العدل'], [
            $property['ownership_document']['attributes']['document_number'], $property['ownership_document']['attributes']['issued_date'],
            $property['ownership_document']['attributes']['ownership_document_type'], $property['ownership_document']['attributes']['issued_by']]);
        $this->assertArrayNotHasKey('scanned_documents', $property['ownership_document']['attributes'], 'electronic deeds need no scan');
        $this->assertSame(['type' => 'individual_entities', 'id' => 'ent-1'], $property['owners']['relationships']['entity']['data']);
        $this->assertSame('property_owner', $property['owners']['attributes']['role']);
        $address = $property['property']['attributes']['address']['attributes'];
        $this->assertSame(['al_riyadh', 'الرياض', 'العليا', '1223', '12533', '2221', 'طريق العليا'],
            [$address['region'], $address['city'], $address['district'], $address['building_number'], $address['postal_code'], $address['additional_number'], $address['street_name']]);
        $this->assertSame(['residential', 'building', 'residential_families', 3, 2, 10],
            [$property['property']['attributes']['contract_type'], $property['property']['attributes']['property_type'], $property['property']['attributes']['property_usage'],
                $property['property']['attributes']['total_floors'], $property['property']['attributes']['associated_facilities']['elevators'], $property['property']['attributes']['associated_facilities']['parking_spaces']]);

        $unit = $this->sent['POST /properties/p-77/units']['data'];
        $this->assertSame('portfolio_units', $unit['type']);
        $this->assertSame(['4', '1', 'apartment', 140.0, 'north', 'finished', 3, 2, '4100230011'], [
            $unit['attributes']['unit_number'], $unit['attributes']['floor_number'], $unit['attributes']['unit_type'], (float) $unit['attributes']['area'],
            $unit['attributes']['unit_direction'], $unit['attributes']['unit_finishing'], $unit['attributes']['amenities']['bedrooms'],
            $unit['attributes']['amenities']['bathrooms_full'], $unit['attributes']['utilities']['electricity_meter']]);
        $this->assertTrue($unit['attributes']['amenities']['is_kitchen_sink_installed']);

        $this->assertSame('p-77', $this->building->fresh()->ejar_property_id);
        $this->assertSame(['p-77', 'u-9'], [$this->unit->fresh()->ejar_property_id, $this->unit->fresh()->ejar_unit_id]);
        $this->assertSame('p-77', $sibling->fresh()->ejar_property_id, 'the other units of the building now belong to the Ejar property');
        $this->assertSame('al_riyadh', $this->building->fresh()->ejar_profile['region'], 'what the office entered is kept for the next unit');
        $this->assertSame('ent-1', $this->unit->owners()->first()->ejar_entity_id);
    }

    public function test_a_failed_unit_step_is_retried_without_creating_the_property_again(): void
    {
        $this->routes['POST /properties/p-77/units'] = ['errors' => [['code' => 'E2201', 'detail' => ['ar' => 'رقم الوحدة مكرر']]]];
        $data = array_replace_recursive(['property' => EjarPortfolio::propertyState($this->unit), 'unit' => EjarPortfolio::unitState($this->unit)], $this->formData());

        Livewire::test(ViewUnit::class, ['record' => $this->unit->id])->mountAction('ejarRegister')->setActionData($data)->callMountedAction()
            ->assertNotified(__('رفضت منصة إيجار التسجيل'));
        $this->assertSame('p-77', $this->building->fresh()->ejar_property_id);
        $this->assertNull($this->unit->fresh()->ejar_unit_id);

        $this->routes['POST /properties/p-77/units'] = ['data' => ['type' => 'portfolio_units', 'attributes' => ['id' => 'u-10']]];
        $this->calls = [];
        Livewire::test(ViewUnit::class, ['record' => $this->unit->id])->mountAction('ejarRegister')
            ->setActionData(['unit' => array_replace(EjarPortfolio::unitState($this->unit->fresh()), ['unit_number' => '4A'])])->callMountedAction();

        $this->assertSame(['POST /properties/p-77/units'], $this->calls, 'the property step is skipped');
        $this->assertSame('u-10', $this->unit->fresh()->ejar_unit_id);
        $this->assertSame('4A', $this->unit->fresh()->ejar_profile['unit_number']);
    }

    public function test_the_other_units_of_a_registered_building_are_registered_together(): void
    {
        $this->building->forceFill(['ejar_property_id' => 'p-77'])->save();
        $five = Unit::create(['title' => 'شقة 5', 'number' => '5', 'offer_type' => 'rent', 'area' => 120, 'rooms' => 2, 'building_id' => $this->building->id, 'ejar_property_id' => 'p-77']);
        $noArea = Unit::create(['title' => 'شقة 6', 'number' => '6', 'offer_type' => 'rent', 'building_id' => $this->building->id, 'ejar_property_id' => 'p-77']);
        $elsewhere = $this->makeUnit(attributes: ['title' => 'فيلا', 'area' => 300]);

        Livewire::test(ListUnits::class)->selectTableRecords([$five->id, $noArea->id, $elsewhere->id])
            ->callAction(TestAction::make('ejarRegisterUnits')->table()->bulk());

        $this->assertSame(['POST /properties/p-77/units'], $this->calls);
        $this->assertSame('u-9', $five->fresh()->ejar_unit_id);
        $this->assertNull($noArea->fresh()->ejar_unit_id, 'no area: Ejar would refuse it');
        $this->assertNull($elsewhere->fresh()->ejar_unit_id, 'its property is not on Ejar yet');
        $this->assertSame('5', $this->sent['POST /properties/p-77/units']['data']['attributes']['unit_number']);
    }

    public function test_an_owner_is_verified_against_ejar(): void
    {
        $owner = $this->unit->owners()->first();
        Livewire::test(ViewOwner::class, ['record' => $owner->id])->callAction('ejarVerify')->assertNotified(__('الهوية مسجلة في إيجار'));

        $owner->refresh();
        $this->assertSame(['ent-1', 'verification_succeed'], [$owner->ejar_entity_id, $owner->ejar_verification]);
        $this->assertNotNull($owner->ejar_verified_at);
        $this->assertSame(['id_number' => '1012345678', 'entity_type' => 'national_id'], $this->sentQuery('GET /individual_entities'));
    }

    public function test_a_contract_documented_outside_the_system_is_linked_and_followed(): void
    {
        $contract = $this->makeRentContract(['start_date' => '2026-01-01', 'end_date' => '2026-12-31'], $this->unit);
        $contract->clients()->first()->update(['id_number' => '2412345678', 'id_type' => 'iqama']);
        $this->routes['GET /contracts'] = function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return isset($query['filters']['contract_number'])
                ? ['data' => ['contract' => ['id' => 'c-5', 'state' => 'registered']]]
                : ['data' => [['type' => 'contracts', 'id' => 'c-5', 'attributes' => ['id' => 'c-5', 'contract_number' => '20154241999', 'version' => 1,
                    'contract_start_date' => '2026-01-01', 'contract_end_date' => '2026-12-31'], 'relationships' => ['contract_portfolio_property' => ['property_name' => 'برج النخيل']]]]];
        };
        $this->routes['GET /contracts/c-5/download_pdf'] = fn () => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']);

        Livewire::test(ViewRentContract::class, ['record' => $contract->id])
            ->mountAction('ejarLinkExisting')
            ->setActionData(['tenant_id_number' => '2412345678', 'lessor_id_number' => '1012345678', 'ejar_contract' => 'c-5|20154241999|1'])
            ->callMountedAction()->assertHasNoActionErrors();

        $contract->refresh();
        $this->assertSame(['c-5', '20154241999', 1, EjarStatus::Documented], [$contract->ejar_contract_id, $contract->ejar_number, $contract->ejar_version, $contract->ejar_status]);
        $this->assertNotNull($contract->getFirstMedia('ejar'));
    }

    public function test_ejar_only_takes_contracts_starting_after_today(): void
    {
        $this->unit->forceFill(['ejar_property_id' => 'p-1', 'ejar_unit_id' => 'u-1'])->save();
        $started = $this->makeRentContract(['start_date' => today()->toDateString(), 'end_date' => today()->addYear()->toDateString()], $this->unit);

        $this->assertContains(__('إيجار يقبل عبر الربط العقود التي تبدأ بعد اليوم فقط. لعقد بدأ بالفعل استخدم "ربط بعقد موجود في إيجار" أو وثّقه من المنصة.'), EjarRegistration::problems($started));
    }

    /** @return array<string, mixed> */
    private function sentQuery(string $key): array
    {
        foreach (Http::recorded() as [$request]) {
            if ($request->method().' '.substr(parse_url($request->url(), PHP_URL_PATH), strlen('/api/v1/ecrs')) === $key) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return $query['filters'] ?? $query;
            }
        }

        return [];
    }
}
