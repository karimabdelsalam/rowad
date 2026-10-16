<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Branding;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The office's logo, name and colour inside its panels and login page. */
class BrandingTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

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

    public function test_monogram_and_default_colour_before_the_office_uploads_anything(): void
    {
        $this->assertSame('ر', Branding::initials());
        $this->assertNull(Branding::logoUrl());
        $this->assertNull(Branding::color());
        $this->assertSame('', Branding::css());

        Settings::set('site_name', 'Nova Homes Co');
        Settings::flush();
        $this->assertSame('NH', Branding::initials());
    }

    public function test_login_page_carries_the_office_identity(): void
    {
        $response = $this->get("{$this->host}/admin/login")->assertOk();
        $response->assertSee('rowad-login-aside', false)->assertSee('مؤسسة الرواد العقارية')->assertSee(__('لوحة الإدارة'));
        $this->get("{$this->host}/owner/login")->assertOk()->assertSee(__('بوابة الملاك'));
    }

    public function test_uploaded_logo_and_brand_colour_reach_the_panel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');
        Settings::set('logo', 'branding/logo.svg');
        Settings::set('brand_color', '#1d4ed8');
        Settings::flush();

        $this->assertStringContainsString('/branding/logo?v=', Branding::logoUrl());
        $this->assertSame('#1d4ed8', Branding::color());
        $this->assertStringContainsString('--primary-600:', Branding::css());
        $this->assertStringContainsString('--rowad-brand:#1d4ed8', Branding::css());

        $user = User::create(['type' => 'staff', 'name' => 'مدير المكتب', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $page = $this->get("{$this->host}/admin")->assertOk();
        $page->assertSee('rowad-brand__logo', false)->assertSee('--rowad-brand:#1d4ed8', false)
            ->assertSee('rowad-quick__btn', false)->assertSee('rowad-top-user', false)->assertSee('مدير');

        $this->get("{$this->host}/branding/logo")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        // a bad colour never reaches the stylesheet
        Settings::set('brand_color', 'red; }');
        Settings::flush();
        $this->assertNull(Branding::color());
    }
}
