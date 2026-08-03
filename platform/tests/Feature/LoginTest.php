<?php

namespace Tests\Feature;

use App\Models\ConsoleUser;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * دخول الكونسول: كلمة المرور، التحقق بخطوتين، وقفل المحاولات.
 *
 * أهم ما تثبته هذه الاختبارات أن كلمة المرور وحدها **لا تفتح جلسة** لحساب
 * محمي بخطوتين، وأن القفل يمنع المحاولة نفسها فلا يمكن سباقه بكلمة صحيحة.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attr = []): ConsoleUser
    {
        return ConsoleUser::create(array_merge([
            'name'     => 'كريم',
            'username' => 'karim',
            'password' => 'console1234',
            'active'   => true,
        ], $attr));
    }

    public function test_correct_credentials_sign_in(): void
    {
        $u = $this->user();

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($u);
    }

    public function test_wrong_password_is_refused_and_logged(): void
    {
        $this->user();

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'nope'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('console_log', ['action' => 'login_failed']);
    }

    public function test_disabled_account_cannot_sign_in(): void
    {
        $this->user(['active' => false]);

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_password_alone_does_not_open_a_session_when_2fa_is_on(): void
    {
        $totp = new TotpService();
        $this->user(['totp_secret' => $totp->newSecret()]);

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234']);

        // كلمة المرور الصحيحة يجب ألا تكفي وحدها
        $this->assertGuest();
        $this->assertNotNull(session('2fa'));
    }

    public function test_correct_totp_completes_the_sign_in(): void
    {
        $totp = new TotpService();
        $secret = $totp->newSecret();
        $u = $this->user(['totp_secret' => $secret]);

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234']);
        $this->post(route('login.totp'), ['code' => $totp->code($secret)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($u);
    }

    public function test_wrong_totp_is_refused(): void
    {
        $totp = new TotpService();
        $this->user(['totp_secret' => $totp->newSecret()]);

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234']);
        $this->post(route('login.totp'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_expired_2fa_step_sends_the_user_back_to_the_password(): void
    {
        $totp = new TotpService();
        $secret = $totp->newSecret();
        $this->user(['totp_secret' => $secret]);

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234']);
        // تجاوز مهلة الخمس دقائق
        session(['2fa' => ['id' => 1, 'at' => now()->subMinutes(6)->timestamp]]);

        $this->post(route('login.totp'), ['code' => $totp->code($secret)])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_lockout_refuses_even_the_correct_password(): void
    {
        $this->user();

        // ثماني محاولات فاشلة من نفس العنوان تُفعّل القفل
        for ($i = 0; $i < 8; $i++) {
            DB::table('console_log')->insert([
                'action' => 'login_failed', 'entity' => 'user', 'ip' => '127.0.0.1',
                'summary' => 'محاولة', 'created_at' => now(),
            ]);
        }

        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234'])
            ->assertSessionHasErrors('username');

        // القفل يمنع المحاولة نفسها، فلا يمكن سباقه بكلمة مرور صحيحة
        $this->assertGuest();
    }

    public function test_lockout_is_scoped_to_the_offending_ip(): void
    {
        $this->user();

        for ($i = 0; $i < 8; $i++) {
            DB::table('console_log')->insert([
                'action' => 'login_failed', 'entity' => 'user', 'ip' => '10.0.0.99',
                'summary' => 'محاولة', 'created_at' => now(),
            ]);
        }

        // عنوان آخر لا يتأثر بقفل غيره
        $this->post(route('login.password'), ['username' => 'karim', 'password' => 'console1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_guests_are_redirected_away_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->user())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
