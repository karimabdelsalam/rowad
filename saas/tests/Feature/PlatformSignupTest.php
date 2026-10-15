<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Public marketing page and self-service office signup on the platform domain. */
class PlatformSignupTest extends TestCase
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

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'مكتب النخبة العقاري',
            'subdomain' => 'nokhba',
            'plan_id' => Plan::where('slug', 'basic')->value('id'),
            'admin_name' => 'فهد',
            'admin_email' => 'fahad@nokhba.test',
            'phone' => '0551234567',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => '1',
        ];
    }

    public function test_home_page_shows_the_active_plans(): void
    {
        Plan::where('slug', 'enterprise')->update(['is_active' => false]);

        $this->get('http://localhost/')->assertOk()
            ->assertSee('الأساسية')->assertSee('الاحترافية')
            ->assertSee('href="/signup?plan='.Plan::where('slug', 'basic')->value('id').'"', false)
            ->assertDontSee('/signup?plan='.Plan::where('slug', 'enterprise')->value('id').'"', false);
    }

    public function test_signup_creates_a_ready_office_on_a_trial(): void
    {
        $this->get('http://localhost/signup')->assertOk();

        $this->post('http://localhost/signup', $this->form())
            ->assertRedirect('http://localhost/signup/done')
            ->assertSessionHasNoErrors();

        $tenant = Tenant::where('name', 'مكتب النخبة العقاري')->firstOrFail();
        $this->assertSame('nokhba.'.config('rowad.tenancy.base_domain'), $tenant->domains()->value('domain'));
        $this->assertSame(Tenant::STATUS_TRIAL, $tenant->status);
        $this->assertSame('0551234567', $tenant->phone);
        $this->assertSame('trialing', Subscription::where('tenant_id', $tenant->id)->value('status'));

        $tenant->run(function () {
            $admin = User::where('email', 'fahad@nokhba.test')->firstOrFail();
            $this->assertTrue($admin->hasRole('admin'));
            $this->assertTrue(Hash::check('secret-pass-1', $admin->password));
        });

        $this->get('http://localhost/signup/done')->assertOk()->assertSee('nokhba.'.config('rowad.tenancy.base_domain'));
    }

    public function test_signup_rejects_a_taken_or_reserved_subdomain_and_bad_input(): void
    {
        $this->post('http://localhost/signup', $this->form())->assertSessionHasNoErrors();

        $this->from('http://localhost/signup')->post('http://localhost/signup', $this->form(['admin_email' => 'other@x.test']))
            ->assertRedirect('http://localhost/signup')->assertSessionHasErrors('subdomain');
        $this->post('http://localhost/signup', $this->form(['subdomain' => 'admin']))->assertSessionHasErrors('subdomain');
        $this->post('http://localhost/signup', $this->form(['subdomain' => 'bad name!']))->assertSessionHasErrors('subdomain');
        $this->post('http://localhost/signup', $this->form(['subdomain' => 'other', 'terms' => null, 'password_confirmation' => 'x']))
            ->assertSessionHasErrors(['terms', 'password']);

        $this->assertSame(1, Tenant::count());
    }

    public function test_a_failed_signup_leaves_nothing_behind_and_can_be_retried(): void
    {
        // e.g. the hosting account's database quota is full, or a migration fails half way
        $fail = true;
        \Illuminate\Support\Facades\Event::listen(\Stancl\Tenancy\Events\DatabaseMigrated::class, function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('database quota reached');
            }
        });

        $this->post('http://localhost/signup', $this->form())->assertSessionHasErrors('name');
        $this->assertSame(0, \App\Models\Tenant::count());
        $this->assertSame(0, \Stancl\Tenancy\Database\Models\Domain::count());
        $this->assertSame(0, \App\Models\Subscription::count());

        $fail = false;
        $this->post('http://localhost/signup', $this->form())->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\Tenant::count());
    }

    public function test_bots_filling_the_hidden_field_create_nothing(): void
    {
        $this->post('http://localhost/signup', $this->form(['website' => 'http://spam.test']))->assertRedirect('http://localhost');

        $this->assertSame(0, Tenant::count());
    }

    /** Several platform domains share the route names; links and redirects must stay on the visitor's domain. */
    public function test_links_stay_on_the_domain_the_visitor_used(): void
    {
        $page = $this->get('http://localhost/signup')->assertOk()->getContent();

        $this->assertStringContainsString('action="/signup"', $page);
        $this->assertStringNotContainsString('127.0.0.1', $page);
    }

    public function test_done_page_without_a_signup_goes_back_to_the_form(): void
    {
        $this->get('http://localhost/signup/done')->assertRedirect('http://localhost/signup');
    }
}
