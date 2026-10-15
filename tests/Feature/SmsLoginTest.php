<?php

namespace Tests\Feature;

use App\Auth\SmsAuthentication;
use App\Models\OutgoingMessage;
use App\Models\User;
use App\Support\Settings;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SmsLoginTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Http::fake(['api.unifonic.com/*' => Http::response(['data' => ['MessageID' => 1]])]);
        Settings::set('sms_provider', 'unifonic');
        Settings::set('sms_username', 'SID');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function staff(array $attributes = []): User
    {
        $user = User::create($attributes + ['name' => 'موظف', 'email' => 'staff@office.test', 'password' => 'secret-password', 'mobile' => '0551112222']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_requirement_follows_office_settings_and_user_switch(): void
    {
        $user = $this->staff();
        $this->assertFalse($user->smsLoginRequired());

        Settings::set('sms_login_staff', true);
        $this->assertTrue($user->fresh()->smsLoginRequired());

        $noMobile = User::create(['name' => 'x', 'email' => 'x@office.test', 'password' => 'secret-password']);
        $this->assertFalse($noMobile->smsLoginRequired()); // cannot receive a code

        Settings::set('sms_login_staff', false);
        $user->toggleEmailAuthentication(true);
        $this->assertTrue($user->fresh()->smsLoginRequired());
    }

    public function test_code_is_sent_by_sms_and_verifies_only_once(): void
    {
        Settings::set('sms_login_staff', true);
        $user = $this->staff();
        \App\Models\MessageTemplate::where('event', 'login_otp')->update(['sms_enabled' => false]); // security codes ignore the switch
        $provider = SmsAuthentication::make();

        $this->assertTrue($provider->sendCode($user));

        $sms = OutgoingMessage::where('event', 'login_otp')->sole();
        $this->assertSame(['sms', '0551112222', 'sent'], [$sms->channel->value, $sms->recipient, $sms->fresh()->status]);
        preg_match('/\d{6}/', $sms->body, $m);
        $this->assertFalse($provider->verifyCode('000000', $user));
        $this->assertTrue($provider->verifyCode($m[0], $user));
        $this->assertFalse($provider->verifyCode($m[0], $user)); // single use
    }

    public function test_login_stops_at_the_sms_challenge(): void
    {
        Settings::set('sms_login_staff', true);
        $this->staff();
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@office.test', 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertGuest();
        $this->assertSame(1, OutgoingMessage::where('event', 'login_otp')->count());
    }
}
