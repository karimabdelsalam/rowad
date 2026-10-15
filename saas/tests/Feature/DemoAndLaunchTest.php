<?php

namespace Tests\Feature;

use App\Enums\MessageChannel;
use App\Jobs\SendOutgoingMessage;
use App\Models\OutgoingMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messaging\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The public demo office and the platform's launch pages. */
class DemoAndLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // central plans
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        Tenant::all()->each->delete();
        parent::tearDown();
    }

    private function demo(): Tenant
    {
        $this->artisan('rowad:demo')->assertSuccessful();
        tenancy()->end();

        return Tenant::whereHas('domains', fn ($q) => $q->where('domain', 'demo.localhost'))->sole();
    }

    public function test_demo_office_one_click_login_for_each_role(): void
    {
        $demo = $this->demo();
        $this->assertTrue($demo->isDemo());

        $this->get('http://demo.localhost/demo/admin')->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->assertSame('admin@demo.test', auth()->user()->email);

        auth()->logout();
        $this->get('http://demo.localhost/demo/owner')->assertRedirect('/owner');
        $this->assertSame('owner@demo.test', auth()->user()->email);
    }

    public function test_one_click_login_does_not_exist_on_real_offices(): void
    {
        $office = app(\App\Services\Platform\OfficeProvisioner::class)->provision(['name' => 'مكتب', 'subdomain' => 'real', 'admin_name' => 'a',
            'admin_email' => 'admin@demo.test', 'admin_password' => 'secret-pass']); // even with the same email
        tenancy()->end();

        $this->get('http://real.localhost/demo/admin')->assertNotFound();
        $this->assertGuest();
        $this->assertFalse($office->isDemo());
    }

    public function test_demo_sends_no_real_messages_and_keeps_its_passwords(): void
    {
        $demo = $this->demo();
        Http::fake();
        tenancy()->initialize($demo);

        $message = OutgoingMessage::create(['channel' => MessageChannel::Sms, 'recipient' => '0500000000', 'body' => 'x', 'status' => 'pending']);
        (new SendOutgoingMessage($message->id))->handle(app(SmsGateway::class));
        $this->assertSame('failed', $message->fresh()->status);
        Http::assertNothingSent();

        $admin = User::where('email', 'admin@demo.test')->sole();
        $admin->update(['password' => 'hacked-password', 'email' => 'mine@x.test', 'name' => 'اسم جديد']);
        $admin->refresh();
        $this->assertSame('admin@demo.test', $admin->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('demo-password', $admin->password));
        $this->assertSame('اسم جديد', $admin->name); // other details can still be tried out
    }

    public function test_landing_shows_ai_and_demo_and_legal_pages_exist(): void
    {
        $this->get('http://localhost/')->assertOk()->assertDontSee('id="demo"', false)->assertSee('اسأل نظامك');

        $this->demo();
        $this->get('http://localhost/')->assertOk()
            ->assertSee('id="demo"', false)
            ->assertSee('http://demo.localhost/demo/owner', false)
            ->assertSee('og:title', false)
            ->assertSee('/terms', false);

        $this->get('http://localhost/terms')->assertOk()->assertSee('شروط الاستخدام')->assertSee('التجربة المجانية');
        $this->get('http://localhost/privacy')->assertOk()->assertSee('نظام حماية البيانات الشخصية');
        $this->get('http://localhost/locale/en');
        $this->get('http://localhost/privacy')->assertOk()->assertSee('Personal Data Protection Law');
        $this->get('http://localhost/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('/privacy', false);
        $this->get('http://localhost/signup')->assertOk()->assertSee('href="/terms"', false);
    }
}
