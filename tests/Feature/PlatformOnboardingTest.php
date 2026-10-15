<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Platform\Resources\Tenants\RelationManagers\DomainsRelationManager;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\OfficeProvisioner;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Tenant> */
    private array $created = [];

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

    private function platformAdmin(): User
    {
        return User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    public function test_platform_admin_creates_an_office_that_is_ready_to_use(): void
    {
        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');
        $plan = Plan::where('slug', 'professional')->first();

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'مكتب الأفق العقاري',
                'email' => 'info@ofuq.test',
                'plan_id' => $plan->id,
                'subdomain' => 'Ofuq',
                'admin_name' => 'سعد',
                'admin_email' => 'saad@ofuq.test',
                'admin_password' => 'very-secret-1',
                'locale' => 'ar',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('name', 'مكتب الأفق العقاري')->sole();
        $this->assertSame('ofuq.localhost', $tenant->domains()->value('domain'));
        $this->assertSame(Tenant::STATUS_TRIAL, $tenant->status);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertSame('trialing', Subscription::where('tenant_id', $tenant->id)->value('status'));

        $tenant->run(function () {
            $admin = User::where('email', 'saad@ofuq.test')->sole();
            $this->assertTrue($admin->hasRole('admin'));
            $this->assertSame('مكتب الأفق العقاري', \App\Support\Settings::get('site_name'));
            $this->assertSame(19, \App\Models\MessageTemplate::count());
        });

        auth()->logout();
        $this->post('http://ofuq.localhost/admin/login')->assertStatus(405); // Livewire login, route exists on the office domain
        $this->get('http://ofuq.localhost/admin/login')->assertOk();
        $this->get('http://ofuq.localhost/')->assertOk()->assertSee('مكتب الأفق العقاري');
    }

    public function test_subdomain_rules(): void
    {
        $provisioner = app(OfficeProvisioner::class);
        $base = ['name' => 'A', 'admin_name' => 'a', 'admin_email' => 'a@a.test', 'admin_password' => 'secret-pass'];

        $provisioner->provision($base + ['subdomain' => 'alpha']);

        foreach (['alpha', 'www', 'ab'] as $bad) {
            try {
                $provisioner->provision($base + ['subdomain' => $bad]);
                $this->fail("{$bad} should be rejected");
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_offices_are_fully_set_up_in_production(): void
    {
        // Artisan asks for confirmation in production; tenant migrate and seed must not stop there.
        $this->app['env'] = 'production';
        $tenant = app(OfficeProvisioner::class)->provision(['name' => 'مكتب الإنتاج', 'subdomain' => 'prod', 'admin_name' => 'a',
            'admin_email' => 'a@prod.test', 'admin_password' => 'secret-pass']);
        $this->app['env'] = 'testing';

        $tenant->run(fn () => $this->assertTrue(User::where('email', 'a@prod.test')->sole()->hasRole('admin')));
    }

    public function test_platform_admin_signs_in_through_the_login_form(): void
    {
        $this->platformAdmin();
        Filament::setCurrentPanel('platform');

        Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm(['email' => 'root@rowad.test', 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticated();
    }

    public function test_platform_admin_links_an_office_to_its_own_domain(): void
    {
        $tenant = app(OfficeProvisioner::class)->provision(['name' => 'مكتب', 'subdomain' => 'own', 'admin_name' => 'a',
            'admin_email' => 'a@a.test', 'admin_password' => 'secret-pass']);
        tenancy()->end();

        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');
        $manager = fn () => Livewire::test(DomainsRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => EditTenant::class]);

        $manager()->callTableAction('create', data: ['domain' => 'not a domain'])->assertHasTableActionErrors(['domain']);
        $manager()->callTableAction('create', data: ['domain' => 'own.localhost'])->assertHasTableActionErrors(['domain']);
        $manager()->callTableAction('create', data: ['domain' => 'https://Own-Office.SA/'])->assertHasNoTableActionErrors();
        $this->assertSame(['own.localhost', 'own-office.sa'], $tenant->domains()->orderBy('id')->pluck('domain')->all());

        $subdomain = $tenant->domains()->where('domain', 'own.localhost')->sole();
        $manager()->assertTableActionHidden('delete', $subdomain);

        auth()->logout();
        $this->get('http://own-office.sa/admin/login')->assertOk();
    }

    public function test_suspended_office_is_blocked_until_reactivated(): void
    {
        $tenant = app(OfficeProvisioner::class)->provision(['name' => 'مكتب', 'subdomain' => 'blocked', 'admin_name' => 'a',
            'admin_email' => 'a@a.test', 'admin_password' => 'secret-pass']);

        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');
        Livewire::test(ListTenants::class)->callAction(TestAction::make('suspend')->table($tenant));
        auth()->logout();

        $this->get('http://blocked.localhost/')->assertStatus(402)->assertSee('الخدمة متوقفة مؤقتًا');
        $this->get('http://blocked.localhost/admin/login')->assertStatus(402);

        app(OfficeProvisioner::class)->activate($tenant->fresh());
        tenancy()->end(); // each production request resolves the office afresh
        $this->get('http://blocked.localhost/admin/login')->assertOk();
    }

    public function test_expired_trial_is_blocked(): void
    {
        $tenant = app(OfficeProvisioner::class)->provision(['name' => 'مكتب', 'subdomain' => 'expired', 'admin_name' => 'a',
            'admin_email' => 'a@a.test', 'admin_password' => 'secret-pass']);
        $tenant->update(['trial_ends_at' => now()->subDay()]);

        $this->get('http://expired.localhost/admin/login')->assertStatus(402);
    }

    public function test_platform_console_is_for_super_admins_on_the_central_domain_only(): void
    {
        $this->actingAs($this->platformAdmin())->get('http://localhost/platform/tenants')->assertOk()->assertSee('المؤسسات المشتركة');

        $regular = User::create(['name' => 'x', 'email' => 'x@x.test', 'password' => 'secret-password']);
        $this->actingAs($regular)->get('http://localhost/platform/tenants')->assertForbidden();
    }
}
