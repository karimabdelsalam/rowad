<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use App\Services\Licensing\LicenseClient;
use App\Services\Licensing\LicenseToken;
use App\Support\PlanLimits;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Licenses issued on aqarnova.com for copies installed on customers' own servers. */
class LicensingTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-06-01 10:00:00');
        $this->keys = LicenseToken::generateKeyPair();
        config(['rowad.license.signing_key' => $this->keys['secret'], 'rowad.license.public_key' => $this->keys['public'],
            'rowad.license.server' => 'http://localhost', 'app.url' => 'https://alrowad.com.sa']);
        File::delete([base_path('storage/app/license.json'), base_path('storage/app/license-instance')]);
        LicenseClient::forget();
    }

    protected function tearDown(): void
    {
        File::delete([base_path('storage/app/license.json'), base_path('storage/app/license-instance')]);
        LicenseClient::forget();
        Date::setTestNow();
        parent::tearDown();
    }

    private function license(array $attributes = []): License
    {
        return License::create($attributes + ['customer_name' => 'شركة الأفق', 'max_activations' => 1, 'max_offices' => 2, 'max_units' => 100,
            'features' => ['owner_portal', 'ai'], 'expires_at' => now()->addYear()]);
    }

    /** Calls from the "customer's server" go to the same app; Http::fake forwards them to the real route. */
    private function relay(): void
    {
        Http::fake(['localhost/api/license/*' => function (Request $request) {
            $response = $this->postJson('http://localhost/api/license/'.basename(parse_url($request->url(), PHP_URL_PATH)), $request->data());

            return Http::response($response->json(), $response->status());
        }]);
    }

    public function test_tokens_are_signed_and_cannot_be_altered(): void
    {
        $token = LicenseToken::sign(['status' => 'active', 'expires_at' => '2027-01-01'], $this->keys['secret']);
        $this->assertSame('active', LicenseToken::verify($token, $this->keys['public'])['status']);

        [$payload, $signature] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode(json_encode(['status' => 'active', 'expires_at' => '2099-01-01'])), '+/', '-_'), '=').'.'.$signature;
        $this->assertNull(LicenseToken::verify($forged, $this->keys['public']));
        $this->assertNull(LicenseToken::verify($token, LicenseToken::generateKeyPair()['public']));
    }

    public function test_customer_server_activates_runs_and_is_limited_by_its_license(): void
    {
        $license = $this->license(['allowed_domains' => ['*.com.sa']]);
        $this->relay();
        config(['rowad.license.mode' => 'self_hosted']);
        $client = app(LicenseClient::class);

        $this->assertSame('not_activated', $client->state()['reason']);
        $this->get('http://localhost/')->assertStatus(402)->assertSee('لم يُفعَّل الترخيص');

        $this->assertTrue($client->activate($license->key)['ok']);
        $this->assertSame(LicenseClient::VALID, $client->state()['state']);
        $this->assertSame('alrowad.com.sa', $license->activations()->sole()->domain);
        $this->get('http://localhost/')->assertOk();

        // License limits apply on top of the plan's.
        $this->assertSame(100, PlanLimits::limit('units'));
        $this->assertSame(2, $client->limit('offices'));
        $this->assertTrue(PlanLimits::allows('ai'));
        $this->assertFalse(PlanLimits::allows('client_portal'));

        // A second server is refused (max_activations = 1).
        File::put(base_path('storage/app/license-instance'), 'other-server');
        LicenseClient::forget();
        $result = $client->activate($license->key);
        $this->assertSame('activation_limit', $result['code']);
    }

    public function test_suspension_reaches_the_customer_on_the_next_check_and_offline_grace_then_stops(): void
    {
        $license = $this->license();
        $this->relay();
        config(['rowad.license.mode' => 'self_hosted']);
        $client = app(LicenseClient::class);
        $client->activate($license->key);

        $license->update(['status' => 'suspended']);
        $this->assertSame(LicenseClient::VALID, $client->state()['state']); // until it checks in
        $this->artisan('rowad:license:check')->assertFailed();
        $this->assertSame('suspended', $client->state()['reason']);
        // (running artisan inside a test switches the app locale, so the text is asserted in the current language)
        $this->get('http://localhost/')->assertStatus(402)->assertSee(LicenseClient::reasonText('suspended'));

        $license->update(['status' => 'active']);
        $this->artisan('rowad:license:check')->assertSuccessful();

        // Server unreachable: works through token_days + grace_days, then stops.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        Date::setTestNow('2026-06-15');
        LicenseClient::forget();
        $this->assertSame(LicenseClient::GRACE, $client->state()['state']);
        $this->get('http://localhost/')->assertOk();
        Date::setTestNow('2026-06-25');
        LicenseClient::forget();
        $this->assertSame('offline_too_long', $client->state()['reason']);
        $this->get('http://localhost/')->assertStatus(402);
    }

    public function test_wrong_key_domain_and_expiry_are_refused(): void
    {
        $license = $this->license(['allowed_domains' => ['other.sa']]);
        $this->postJson('http://localhost/api/license/activate', ['key' => 'AQN-NOPE', 'domain' => 'x.sa', 'instance_id' => 'i'])->assertNotFound();
        $this->postJson('http://localhost/api/license/activate', ['key' => $license->key, 'domain' => 'alrowad.com.sa', 'instance_id' => 'i'])
            ->assertForbidden()->assertJsonPath('code', 'domain_not_allowed');

        $license->update(['allowed_domains' => null, 'expires_at' => now()->subDay()]);
        $this->postJson('http://localhost/api/license/activate', ['key' => $license->key, 'domain' => 'alrowad.com.sa', 'instance_id' => 'i'])
            ->assertForbidden()->assertJsonPath('code', 'expired');
    }

    public function test_platform_admin_issues_renews_and_sees_installations(): void
    {
        $plan = Plan::create(['slug' => 'pro', 'name_ar' => 'احترافية', 'name_en' => 'Pro', 'monthly_price' => 1, 'max_units' => 300, 'features' => ['owner_portal']]);
        $this->actingAs(User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('platform');

        Livewire::test(\App\Filament\Platform\Resources\Licenses\Pages\CreateLicense::class)
            ->fillForm(['customer_name' => 'مكتب النخبة', 'plan_id' => $plan->id, 'max_activations' => 2, 'max_offices' => 1])
            ->call('create')->assertHasNoFormErrors();
        $license = License::sole();
        $this->assertMatchesRegularExpression('/^AQN(-[A-Z2-9]{5}){4}$/', $license->key);
        $this->assertSame(['owner_portal'], $license->effectiveFeatures());

        $this->postJson('http://localhost/api/license/activate', ['key' => $license->key, 'domain' => 'nokhba.sa', 'instance_id' => 'srv-1', 'version' => '1.2'])->assertOk();

        Livewire::test(\App\Filament\Platform\Resources\Licenses\Pages\ViewLicense::class, ['record' => $license->getRouteKey()])
            ->assertSee($license->key)->assertSee('1 / 2')
            ->callAction('renew', ['expires_at' => '2028-01-01 00:00:00'])->assertHasNoActionErrors();
        $this->assertSame('2028-01-01', $license->fresh()->expires_at->toDateString());

        Livewire::test(\App\Filament\Platform\Resources\Licenses\RelationManagers\ActivationsRelationManager::class,
            ['ownerRecord' => $license, 'pageClass' => \App\Filament\Platform\Resources\Licenses\Pages\ViewLicense::class])
            ->assertSee('nokhba.sa')->assertSee('1.2');
    }

    public function test_saas_platform_ignores_licensing_and_hides_the_license_page(): void
    {
        config(['rowad.license.mode' => 'saas']);
        $this->assertSame(LicenseClient::VALID, app(LicenseClient::class)->state()['state']);
        $this->assertFalse(\App\Filament\Platform\Pages\LicensePage::shouldRegisterNavigation());
        $this->get('http://localhost/')->assertOk();
    }
}
