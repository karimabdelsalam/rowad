<?php

namespace Tests\Feature;

use App\Filament\Pages\PortalDashboard;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The portals as an installable phone app: manifest in the office's identity, service worker, offline page, tab bar. */
class PortalAppTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy(['name' => 'مؤسسة الرواد العقارية']);
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_manifest_carries_the_office_identity_per_portal(): void
    {
        Settings::set('brand_color', '#1d4ed8');

        $manifest = $this->get("{$this->host}/manifest/client.webmanifest")->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('مؤسسة الرواد العقارية — بوابة المستأجرين', $manifest['name']);
        $this->assertSame('/client?source=pwa', $manifest['start_url']);
        $this->assertSame('/client', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#1d4ed8', $manifest['theme_color']);
        $this->assertSame('rtl', $manifest['dir']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        $this->assertSame('/owner', $this->get("{$this->host}/manifest/owner.webmanifest")->json('scope'));
        $this->get("{$this->host}/manifest/platform.webmanifest")->assertNotFound();

        $icon = $this->get("{$this->host}/app/icon.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('#1d4ed8', $icon->getContent());
        $this->assertStringContainsString('>ر<', $icon->getContent());
    }

    public function test_service_worker_and_offline_page(): void
    {
        $sw = $this->get("{$this->host}/sw.js")->assertOk()->assertHeader('Service-Worker-Allowed', '/');
        $this->assertStringContainsString('application/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString("if (req.method !== 'GET') return;", $sw->getContent());
        $this->get("{$this->host}/app/offline")->assertOk()->assertSee('لا يوجد اتصال بالإنترنت');
    }

    public function test_portal_pages_link_the_manifest_and_show_the_tab_bar(): void
    {
        $client = $this->makeClient();
        $user = app(PortalAccounts::class)->issue($client, 'client1', 'secret-password')['user'];
        $this->actingAs($user);

        $page = $this->get("{$this->host}/client")->assertOk();
        $page->assertSee('<link rel="manifest" href="/manifest/client.webmanifest">', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js'", false)
            ->assertSee('<nav class="rowad-tabbar"', false)->assertSee('ثبّت التطبيق على جوالك');

        // the login page links the manifest too, but has no tab bar
        auth()->logout();
        $this->get("{$this->host}/client/login")->assertOk()->assertSee('/manifest/client.webmanifest', false)->assertDontSee('<nav class="rowad-tabbar"', false);
    }
}
