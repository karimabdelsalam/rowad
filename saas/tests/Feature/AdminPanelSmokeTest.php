<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Renders every admin resource page on an office subdomain as an admin user. */
class AdminPanelSmokeTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create(['type' => 'staff', 'name' => 'مدير المكتب', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_every_admin_resource_page_renders(): void
    {
        $this->actingAs($this->admin());
        $unit = $this->makeUnit();
        $this->makeRentContract([], $unit);

        $this->get("{$this->host}/admin")->assertOk();

        $panel = Filament::getPanel('admin');
        $this->assertNotEmpty($panel->getResources());

        foreach ($panel->getResources() as $resource) {
            foreach (['index', 'create'] as $page) {
                if (! $resource::hasPage($page)) {
                    continue;
                }
                $url = $this->host.parse_url($resource::getUrl($page, panel: 'admin'), PHP_URL_PATH);
                $response = $this->get($url);
                $this->assertSame(200, $response->status(), "{$resource} {$page} returned {$response->status()}: ".mb_substr(strip_tags((string) $response->exception?->getMessage()), 0, 300));
            }
        }

        $response = $this->get($this->host.parse_url(\App\Filament\Admin\Resources\Units\UnitResource::getUrl('view', ['record' => $unit], panel: 'admin'), PHP_URL_PATH));
        $response->assertOk()->assertSee('شقة 101');
    }

    public function test_viewer_role_cannot_open_create_pages(): void
    {
        $user = User::create(['type' => 'staff', 'name' => 'مشاهد', 'email' => 'viewer@office.test', 'password' => 'secret-password']);
        $user->assignRole('viewer');
        $this->actingAs($user);

        $this->get("{$this->host}/admin/units")->assertOk();
        $this->get("{$this->host}/admin/units/create")->assertForbidden();
    }

    public function test_owner_account_cannot_enter_the_admin_panel(): void
    {
        $owner = User::create(['type' => 'owner', 'name' => 'مالك', 'email' => 'owner@office.test', 'password' => 'secret-password']);
        $this->actingAs($owner);

        $this->get("{$this->host}/admin")->assertForbidden();
    }

    public function test_admin_panel_is_not_served_on_central_domain(): void
    {
        $this->get('http://localhost/admin/login')->assertNotFound();
    }
}
