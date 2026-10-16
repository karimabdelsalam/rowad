<?php

namespace Tests\Feature;

use App\Filament\Platform\Pages\PlatformSettingsPage;
use App\Filament\Platform\Pages\SystemHealthPage;
use App\Filament\Platform\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Platform\Resources\Payments\Pages\ListPayments;
use App\Filament\Platform\Resources\Tenants\Pages\ViewTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Platform\SubscriptionManager;
use App\Support\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** The platform console (v2): settings, office detail page and its actions, payments, admins, health, "open as admin". */
class PlatformConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSettings::flush();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        Tenant::all()->each->delete();
        PlatformSettings::flush();
        parent::tearDown();
    }

    private function platformAdmin(): User
    {
        $admin = User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('platform');

        return $admin;
    }

    private function office(string $subdomain = 'ofuq'): Tenant
    {
        return app(OfficeProvisioner::class)->provision([
            'name' => 'مكتب الأفق', 'subdomain' => $subdomain, 'plan_id' => Plan::where('slug', 'professional')->value('id'),
            'admin_name' => 'سعد', 'admin_email' => 'saad@ofuq.test', 'admin_password' => 'very-secret-1',
        ]);
    }

    public function test_settings_page_saves_and_closes_signup(): void
    {
        $this->platformAdmin();
        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['signup_open' => false, 'signup_closed_message' => 'نفتح قريبًا', 'reserved_subdomains' => ['shop'], 'trial_days' => 30, 'announcement_enabled' => true, 'announcement_text' => 'صيانة الليلة'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertFalse(PlatformSettings::signupOpen());
        $this->assertSame(30, PlatformSettings::trialDays(Plan::first()));
        $this->assertContains('shop', PlatformSettings::reservedSubdomains());
        $this->assertSame('صيانة الليلة', PlatformSettings::announcement()['text']);

        $this->get('http://localhost/signup')->assertOk()->assertSee('التسجيل مغلق حاليًا')->assertSee('نفتح قريبًا');
        $this->post('http://localhost/signup', ['name' => 'x'])->assertForbidden();

        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(OfficeProvisioner::class)->normaliseSubdomain('shop');
    }

    public function test_trial_length_from_settings_applies_to_new_offices_and_banner_shows_in_office_panel(): void
    {
        PlatformSettings::save(['trial_days' => 3, 'announcement_enabled' => true, 'announcement_text' => 'تحديث جديد الليلة']);
        $office = $this->office();
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $office->trial_ends_at->timestamp, 5);

        $office->run(fn () => $this->actingAs(User::where('email', 'saad@ofuq.test')->sole()));
        $this->get('http://ofuq.localhost/admin')->assertOk()->assertSee('تحديث جديد الليلة');
    }

    public function test_office_detail_page_and_its_actions(): void
    {
        $this->platformAdmin();
        $office = $this->office();
        app(SubscriptionManager::class)->record($office, Plan::where('slug', 'professional')->first(), 'monthly', 299, null, 'TRX-1');
        $office->refresh();

        $page = Livewire::test(ViewTenant::class, ['record' => $office->getKey()])->assertOk()
            ->assertSee('مكتب الأفق')->assertSee('ofuq.localhost')->assertSee('saad@ofuq.test')->assertSee('TRX-1');

        $page->callAction('changeSubdomain', ['subdomain' => 'Horizon'])->assertHasNoActionErrors();
        $this->assertSame('horizon.localhost', $office->domains()->value('domain'));

        $page->callAction('changeSubdomain', ['subdomain' => 'admin']); // reserved: refused with a notification, nothing changes
        $this->assertSame('horizon.localhost', $office->domains()->value('domain'));

        $trial = $this->office('trial');
        Livewire::test(ViewTenant::class, ['record' => $trial->getKey()])
            ->callAction('extendTrial', ['days' => 10])->assertHasNoActionErrors();
        $this->assertEqualsWithDelta($trial->trial_ends_at->addDays(10)->timestamp, $trial->fresh()->trial_ends_at->timestamp, 5);
    }

    public function test_payments_list_and_health_page_render(): void
    {
        $this->platformAdmin();
        $office = $this->office();
        app(SubscriptionManager::class)->record($office, Plan::where('slug', 'professional')->first(), 'yearly', 2990, null, 'TRX-9');

        Livewire::test(ListPayments::class)->assertOk()->assertSee('مكتب الأفق')->assertSee('TRX-9')->assertSee('2,990.00');
        Livewire::test(SystemHealthPage::class)->assertOk()->assertSee('إصدار PHP');
    }

    public function test_platform_admins_are_created_from_the_console(): void
    {
        $this->platformAdmin();
        Livewire::test(CreateAdmin::class)
            ->fillForm(['name' => 'نورة', 'email' => 'noura@rowad.test', 'password' => 'strong-password-1', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();

        $noura = User::where('email', 'noura@rowad.test')->sole();
        $this->assertTrue($noura->is_super_admin);
        $this->assertTrue($noura->canAccessPanel(Filament::getPanel('platform')));
    }

    public function test_platform_operator_opens_an_office_with_a_signed_link_only(): void
    {
        $office = $this->office();
        $staffId = $office->run(fn () => User::where('email', 'saad@ofuq.test')->value('id'));

        $this->get("http://ofuq.localhost/platform-access/{$staffId}")->assertForbidden();

        URL::forceRootUrl('http://ofuq.localhost');
        $url = URL::temporarySignedRoute('platform.access', now()->addMinutes(2), ['user' => $staffId]);
        URL::forceRootUrl(null);

        $this->get($url)->assertRedirect('/admin');
        $this->get('http://ofuq.localhost/admin')->assertOk()->assertSee('سعد');
    }
}
