<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\User;
use App\Services\Portal\PortalAccounts;
use App\Support\Maps;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Legacy gmap_key (location maps) and dashboard/qrcode printable sheets. */
class MapsAndQrSheetsTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = 'http://'.$this->initializeTenancy()->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_maps_use_the_google_key_when_set_and_openstreetmap_otherwise(): void
    {
        $this->assertNull(Maps::embedUrl(null, 46.6));
        $this->assertStringStartsWith('https://www.openstreetmap.org/export/embed.html', Maps::embedUrl(24.69, 46.68));

        Settings::set('google_maps_key', 'test-key');
        $url = Maps::embedUrl(24.69, 46.68);
        $this->assertStringStartsWith('https://www.google.com/maps/embed/v1/place', $url);
        $this->assertStringContainsString('key=test-key', $url);
    }

    public function test_qr_sheets_are_printable_by_staff_only(): void
    {
        $staff = User::create(['name' => 'a', 'email' => 'a@office.test', 'password' => 'secret-password']);
        $staff->assignRole('admin');
        $this->actingAs($staff)->get("{$this->host}/print/qr/owner")->assertOk()->assertSee('/owner')->assertSee('planova.com.eg');
        $this->get("{$this->host}/print/qr/nope")->assertNotFound();

        $owner = Owner::create(['first_name' => 'مالك']);
        app(PortalAccounts::class)->issue($owner, 'owner@office.test', 'chosen-pass-9');
        $this->actingAs($owner->fresh()->user)->get("{$this->host}/print/qr/client")->assertForbidden();
    }
}
