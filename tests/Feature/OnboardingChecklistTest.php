<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\GettingStarted;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The getting-started checklist a new office sees on its dashboard. */
class OnboardingChecklistTest extends TestCase
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

    public function test_checklist_tracks_progress_and_can_be_dismissed(): void
    {
        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertTrue(GettingStarted::canView());
        $this->assertSame(6, GettingStarted::remaining()); // the website is on by default
        $this->get("{$this->host}/admin")->assertOk()->assertSee('جهّز مؤسستك في 7 خطوات')->assertSee('أنجزت 1 من 7')->assertSee('أضف أول مالك');

        // doing the work ticks the boxes
        $unit = $this->makeUnit(); // creates an owner too
        $this->makeRentContract([], $unit);
        Settings::set('sms_provider', 'unifonic', 'sms');
        Settings::flush();
        $steps = collect(GettingStarted::steps())->keyBy('key');
        $this->assertTrue($steps['owner']['done']);
        $this->assertTrue($steps['unit']['done']);
        $this->assertTrue($steps['contract']['done']);
        $this->assertTrue($steps['sms']['done']);
        $this->assertFalse($steps['identity']['done']);
        $this->assertSame(2, GettingStarted::remaining());
        $this->get("{$this->host}/admin")->assertOk()->assertSee('أنجزت 5 من 7');

        // a viewer never sees it
        $viewer = User::create(['type' => 'staff', 'name' => 'مشاهد', 'email' => 'viewer@office.test', 'password' => 'secret-password']);
        $viewer->assignRole('viewer');
        $this->actingAs($viewer);
        $this->assertFalse(GettingStarted::canView());

        // the admin can hide it for good
        $this->actingAs($admin);
        Livewire::test(GettingStarted::class)->call('dismiss');
        $this->assertFalse(GettingStarted::canView());
        $this->get("{$this->host}/admin")->assertOk()->assertDontSee('جهّز مؤسستك');
    }
}
