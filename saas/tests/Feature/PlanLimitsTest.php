<?php

namespace Tests\Feature;

use App\Enums\MessageChannel;
use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Pages\Subscription;
use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Filament\Admin\Resources\Units\Pages\CreateUnit;
use App\Filament\Admin\Resources\Units\Pages\ListUnits;
use App\Jobs\SendOutgoingMessage;
use App\Models\OutgoingMessage;
use App\Models\Plan;
use App\Models\User;
use App\Services\Portal\PortalAccounts;
use App\Support\PlanLimits;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Legacy updatecore plan checks: quotas stop new records, features switch services off, and the office can ask for more. */
class PlanLimitsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = Plan::create(['slug' => 'mini', 'name_ar' => 'مصغرة', 'name_en' => 'Mini', 'monthly_price' => 99, 'is_active' => true,
            'max_units' => 2, 'max_users' => 5, 'max_sms_per_month' => 1, 'features' => ['client_portal']]);
        Plan::create(['slug' => 'pro', 'name_ar' => 'احترافية', 'name_en' => 'Pro', 'monthly_price' => 399, 'is_active' => true, 'features' => array_keys(PlanLimits::FEATURES)]);
        Plan::create(['slug' => 'starter', 'name_ar' => 'مبتدئة', 'name_en' => 'Starter', 'monthly_price' => 49, 'is_active' => true, 'features' => []]);
        $tenant = $this->initializeTenancy(['plan_id' => $this->plan->id]);
        $this->host = 'http://'.$tenant->domains()->value('domain');
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password', 'type' => 'staff']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_units_stop_at_the_plan_limit(): void
    {
        $this->makeUnit();
        $this->actingAs($this->admin());
        Livewire::test(ListUnits::class)->assertActionEnabled('create');
        $this->assertFalse(PlanLimits::reached('units'));

        $this->makeUnit();
        $this->assertTrue(PlanLimits::reached('units'));
        Livewire::test(ListUnits::class)->assertActionDisabled('create');
        Livewire::test(CreateUnit::class)->assertRedirect(ListUnits::getUrl());

        $this->expectException(BusinessRuleException::class);
        PlanLimits::ensureRoomFor('units');
    }

    public function test_no_plan_means_no_limits(): void
    {
        $this->tenant->update(['plan_id' => null]);
        $this->tenant->unsetRelation('plan');

        $this->assertNull(PlanLimits::limit('units'));
        $this->assertTrue(PlanLimits::allows('owner_portal'));
        $this->assertFalse(PlanLimits::reached('units'));
    }

    public function test_services_outside_the_plan_are_switched_off(): void
    {
        $owner = $this->makeUnit()->owners()->first();
        $user = app(PortalAccounts::class)->issue($owner, 'owner@office.test', 'secret-pass-1')['user'];

        $this->actingAs($user)->get("{$this->host}/owner")
            ->assertForbidden()->assertSee('هذه الخدمة غير متاحة في باقة المؤسسة الحالية.');
        $this->getJson("{$this->host}/api/v1/units")->assertForbidden();

        $this->actingAs($this->admin());
        $this->assertFalse(CampaignResource::canAccess());
    }

    public function test_monthly_sms_quota_stops_sending(): void
    {
        Http::fake(['api.unifonic.com/*' => Http::response(['data' => ['MessageID' => 1]])]);
        Settings::set('sms_provider', 'unifonic');
        Settings::set('sms_username', 'APP-SID');
        Settings::set('sms_password', 'x');

        $first = OutgoingMessage::create(['channel' => MessageChannel::Sms, 'recipient' => '0500000001', 'body' => 'أولى', 'status' => 'pending']);
        $second = OutgoingMessage::create(['channel' => MessageChannel::Sms, 'recipient' => '0500000002', 'body' => 'ثانية', 'status' => 'pending']);
        (new SendOutgoingMessage($first->id))->handle(app(\App\Services\Messaging\SmsGateway::class));
        (new SendOutgoingMessage($second->id))->handle(app(\App\Services\Messaging\SmsGateway::class));

        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertStringContainsString('SMS', $second->fresh()->error);
        Http::assertSentCount(1);
    }

    public function test_subscription_page_shows_usage_and_sends_an_upgrade_request(): void
    {
        Mail::fake();
        config(['rowad.platform_admin.email' => 'platform@aqarnova.test']);
        $this->makeUnit();
        $pro = Plan::where('slug', 'pro')->first();

        $this->actingAs($this->admin());
        Livewire::test(Subscription::class)
            ->assertOk()
            ->assertSee('مصغرة')
            ->assertSee('1 من 2')
            ->callAction('requestUpgrade', ['plan_id' => $pro->id, 'note' => 'نحتاج وحدات أكثر'])
            ->assertHasNoActionErrors()
            ->assertSee('طلبت الترقية إلى احترافية');

        $this->assertSame($pro->id, $this->tenant->fresh()->upgrade_request['plan_id']);
        $this->assertSame('نحتاج وحدات أكثر', $this->tenant->fresh()->upgrade_request['note']);
        $this->assertSame([$pro->id], array_keys(Subscription::upgradeOptions()));

        // The top plan has nothing to upgrade to.
        $this->tenant->update(['plan_id' => $pro->id]);
        $this->tenant->unsetRelation('plan');
        Livewire::test(Subscription::class)->assertActionHidden('requestUpgrade');
    }
}
