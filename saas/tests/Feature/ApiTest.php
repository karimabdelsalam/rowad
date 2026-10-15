<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Installment;
use App\Models\LoginActivity;
use App\Models\OutgoingMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Installments\InstallmentCollector;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Mobile API (legacy ?output=json + ACCESS-TOKEN) on the office domain. */
class ApiTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $api;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-10');
        $tenant = $this->initializeTenancy();
        $this->api = 'http://'.$tenant->domains()->value('domain').'/api/v1';
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function staff(string $role = 'admin', array $attributes = []): User
    {
        $user = User::create($attributes + ['name' => 'موظف', 'username' => 'staff1', 'email' => 'staff@office.test', 'password' => 'secret-password', 'mobile' => '0551112222']);
        $user->assignRole($role);

        return $user;
    }

    private function token(string $login, string $password = 'secret-password'): string
    {
        $this->app['auth']->forgetGuards(); // each app request resolves its own user, as in production

        return $this->postJson("{$this->api}/auth/login", ['login' => $login, 'password' => $password, 'device_name' => 'iPhone'])
            ->assertOk()->json('token');
    }

    /** Rented unit with a monthly contract; first installment collected. */
    private function rented(int $rent = 1000): array
    {
        $unit = $this->makeUnit([[100, 100]], ['title' => "وحدة {$rent}"]);
        $client = $this->makeClient();
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => $rent,
        ], [$client->id => 100]);
        app(InstallmentCollector::class)->collect($contract->installments()->orderBy('due_date')->first());

        return compact('unit', 'client', 'contract');
    }

    public function test_staff_signs_in_by_username_and_reads_office_data(): void
    {
        $this->staff();
        $this->rented();

        $this->postJson("{$this->api}/auth/login", ['login' => 'staff1', 'password' => 'nope', 'device_name' => 'x'])->assertStatus(422);
        $token = $this->token('staff1');
        $this->assertSame([false, true], LoginActivity::orderBy('id')->pluck('successful')->all());

        $auth = ['Authorization' => "Bearer {$token}"];
        $this->getJson("{$this->api}/me", $auth)->assertOk()->assertJsonPath('data.username', 'staff1')->assertJsonPath('data.type.value', 'staff');
        $this->getJson("{$this->api}/units", $auth)->assertOk()->assertJsonPath('data.0.title', 'وحدة 1000')->assertJsonPath('data.0.status.value', 'rented');
        $this->getJson("{$this->api}/contracts/rent", $auth)->assertOk()->assertJsonCount(1, 'data');
        $contractId = $this->getJson("{$this->api}/contracts/rent", $auth)->json('data.0.id');
        $this->getJson("{$this->api}/contracts/rent/{$contractId}", $auth)->assertOk()->assertJsonCount(12, 'data.installments');
        $this->getJson("{$this->api}/installments?status=overdue", $auth)->assertOk()->assertJsonCount(2, 'data'); // Feb and Mar 1st
        $this->getJson("{$this->api}/installments?status=collected", $auth)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$this->api}/clients", $auth)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$this->api}/dashboard", $auth)->assertOk()->assertJsonStructure(['data' => ['collections']]);
    }

    public function test_collecting_from_the_app_uses_the_same_rules_and_permissions(): void
    {
        $this->staff('viewer');
        $installment = $this->rented()['contract']->installments()->where('is_collected', false)->orderBy('due_date')->first();
        $auth = ['Authorization' => 'Bearer '.$this->token('staff1')];

        $this->postJson("{$this->api}/installments/{$installment->id}/collect", [], $auth)->assertForbidden();

        User::where('username', 'staff1')->first()->syncRoles('accountant');
        $this->app['auth']->forgetGuards();
        $this->postJson("{$this->api}/installments/{$installment->id}/collect", ['amount' => 5000], $auth)->assertOk(); // more than due = full collection
        $this->assertTrue($installment->refresh()->is_collected);

        $next = Installment::where('is_collected', false)->orderBy('due_date')->first();
        $this->postJson("{$this->api}/installments/{$next->id}/collect", ['amount' => 400], $auth)
            ->assertOk()->assertJsonPath('data.paid_amount', '400.00')->assertJsonPath('data.remaining', '600.00')->assertJsonPath('data.is_collected', false);
    }

    public function test_sms_login_requires_the_code_sent_to_the_mobile(): void
    {
        Settings::set('sms_login_staff', true);
        $this->staff();

        $this->postJson("{$this->api}/auth/login", ['login' => 'staff@office.test', 'password' => 'secret-password', 'device_name' => 'x'])
            ->assertStatus(202)->assertJsonPath('mfa_required', true)->assertJsonMissingPath('token');

        preg_match('/\d{6}/', OutgoingMessage::where('event', 'login_otp')->sole()->body, $m);
        $this->postJson("{$this->api}/auth/login", ['login' => 'staff@office.test', 'password' => 'secret-password', 'device_name' => 'x', 'code' => '000000'])->assertStatus(422);
        $this->postJson("{$this->api}/auth/login", ['login' => 'staff@office.test', 'password' => 'secret-password', 'device_name' => 'x', 'code' => $m[0]])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_owner_and_client_apps_only_see_their_own_records(): void
    {
        $a = $this->rented(1111);
        $b = $this->rented(2222);
        $accounts = app(PortalAccounts::class);
        $accounts->issue($a['unit']->owners()->first(), 'ownerA', 'secret-password');
        $accounts->issue($b['client'], 'clientB@office.test', 'secret-password');

        $owner = ['Authorization' => 'Bearer '.$this->token('ownerA')];
        $this->getJson("{$this->api}/owner/units", $owner)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'وحدة 1111');
        $statement = $this->getJson("{$this->api}/owner/statement", $owner)->assertOk()->json('data');
        $this->assertNotEmpty($statement);
        $this->assertSame([], array_values(array_filter($statement, fn ($e) => $e['unit']['id'] !== $a['unit']->id)));
        $this->getJson("{$this->api}/owner/summary", $owner)->assertOk()->assertJsonPath('data.units', 1);
        $this->getJson("{$this->api}/units", $owner)->assertForbidden();
        $this->getJson("{$this->api}/client/contracts", $owner)->assertForbidden();

        $client = ['Authorization' => 'Bearer '.$this->token('clientB@office.test')];
        $this->app['auth']->forgetGuards();
        $this->getJson("{$this->api}/client/contracts", $client)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b['contract']->id);
        $this->getJson("{$this->api}/client/installments", $client)->assertOk()->assertJsonPath('data.0.amount', '2222.00');

        // ticket ownership comes from the token, and foreign units are dropped
        $this->postJson("{$this->api}/client/tickets", ['type' => 'problem', 'unit_id' => $a['unit']->id, 'subject' => 'تسريب', 'message' => 'يوجد تسريب'], $client)
            ->assertCreated()->assertJsonPath('data.unit_id', null);
        $this->assertSame($b['client']->id, SupportTicket::sole()->client_id);
        $this->app['auth']->forgetGuards();
        $this->getJson("{$this->api}/owner/tickets", $owner)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_device_tokens_follow_the_signed_in_user_and_logout_revokes_the_token(): void
    {
        $this->staff();
        $other = $this->staff('admin', ['username' => 'staff2', 'email' => 'staff2@office.test']);
        $first = ['Authorization' => 'Bearer '.$this->token('staff1')];

        $this->postJson("{$this->api}/devices", ['token' => 'fcm-abc', 'platform' => 'android'], $first)->assertCreated();
        $second = ['Authorization' => 'Bearer '.$this->token('staff2')];
        $this->postJson("{$this->api}/devices", ['token' => 'fcm-abc', 'platform' => 'android'], $second)->assertCreated();
        $this->assertSame([$other->id], DeviceToken::pluck('user_id')->all());

        $this->app['auth']->forgetGuards();
        $this->postJson("{$this->api}/auth/logout", [], $first)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson("{$this->api}/me", $first)->assertUnauthorized();
    }

    public function test_suspended_office_gets_a_json_error(): void
    {
        $this->tenant->update(['status' => \App\Models\Tenant::STATUS_SUSPENDED]);
        $this->postJson("{$this->api}/auth/login", ['login' => 'x', 'password' => 'y', 'device_name' => 'z'])->assertStatus(402)->assertJsonStructure(['message']);
    }
}
