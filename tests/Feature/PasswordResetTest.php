<?php

namespace Tests\Feature;

use App\Filament\Auth\RequestPasswordReset;
use App\Models\OutgoingMessage;
use App\Models\User;
use App\Services\Portal\PortalAccounts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Legacy resetpwd by email or by SMS code (resetbyotp), for staff and portal accounts. */
class PasswordResetTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function sentCode(): string
    {
        preg_match('/\d{6}/', OutgoingMessage::where('event', 'password_reset_otp')->latest('id')->firstOrFail()->body, $m);

        return $m[0];
    }

    public function test_owner_resets_password_with_sms_code_from_the_portal(): void
    {
        $owner = $this->makeUnit()->owners()->first();
        $owner->update(['mobile' => '0551234567']);
        $user = app(PortalAccounts::class)->issue($owner, 'owner-login', 'old-password-1')['user'];
        $user->update(['mobile' => '0551234567']);
        Filament::setCurrentPanel('owner');

        $page = Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => '+966 55 123 4567'])
            ->call('request')
            ->assertSet('codeSent', true);
        $this->assertSame('sms', OutgoingMessage::where('event', 'password_reset_otp')->sole()->channel->value);

        $page->fillForm(['code' => '000000', 'password' => 'New-password-2026', 'password_confirmation' => 'New-password-2026'])
            ->call('request')->assertNoRedirect();
        $page->fillForm(['code' => $this->sentCode(), 'password' => 'New-password-2026', 'password_confirmation' => 'New-password-2026'])
            ->call('request')->assertRedirect(Filament::getLoginUrl());

        $this->assertTrue(Hash::check('New-password-2026', $user->fresh()->password));
    }

    public function test_unknown_mobile_gets_the_same_answer_and_no_message(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => '0599999999'])->call('request')->assertSet('codeSent', true);
        $this->assertSame(0, OutgoingMessage::count());
    }

    public function test_email_still_sends_a_reset_link(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'موظف', 'email' => 'staff@office.test', 'password' => 'secret-password']);
        Filament::setCurrentPanel('admin');

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'staff@office.test'])->call('request')->assertSet('codeSent', false);
        Notification::assertSentTo($user, \Filament\Auth\Notifications\ResetPassword::class);
    }

    public function test_api_reset_by_sms_code_revokes_existing_tokens(): void
    {
        $user = User::create(['name' => 'موظف', 'username' => 'staff1', 'password' => 'secret-password', 'mobile' => '0551112222']);
        $user->createToken('old-phone');
        $api = 'http://'.$this->tenant->domains()->value('domain').'/api/v1';

        $this->postJson("{$api}/auth/password/code", ['mobile' => '966551112222'])->assertStatus(202);
        $this->postJson("{$api}/auth/password/reset", ['mobile' => '0551112222', 'code' => '111111', 'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])->assertStatus(422);
        $this->postJson("{$api}/auth/password/reset", ['mobile' => '0551112222', 'code' => $this->sentCode(), 'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])->assertOk();

        $this->assertTrue(Hash::check('Brand-new-pass-1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }
}
