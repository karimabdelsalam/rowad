<?php

namespace Tests\Feature;

use App\Filament\Support\CommandSearch;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The top search box (Ctrl+K): records across the office, pages by name and "new …" actions, within the user's permissions. */
class CommandSearchTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /** @return array<string, list<string>> category => result titles */
    private function search(string $query): array
    {
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        return app(CommandSearch::class)->getResults($query)->getCategories()
            ->map(fn ($results) => collect($results)->map(fn (GlobalSearchResult $r) => (string) $r->title)->values()->all())
            ->all();
    }

    public function test_records_pages_and_actions_are_found(): void
    {
        $this->actingAs(User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]));
        $contract = $this->makeRentContract(['number' => 'RC-2026-00077'], $this->makeUnit(attributes: ['title' => 'شقة الياسمين', 'number' => 'A-12']));

        // a tenant's name finds the client and the contract they signed
        $results = $this->search('تجريبي');
        $this->assertContains('عميل تجريبي', $results[__('العملاء (مستأجرون ومشترون)')]);
        $this->assertContains(__('عقد إيجار :number', ['number' => $contract->number]), $results[__('عقود الإيجار')]);
        $this->assertSame(['شقة الياسمين · A-12'], $this->search('الياسمين')[__('الوحدات')]);

        // pages and actions by name, forgiving of hamza and taa marbuta
        $shortcuts = $this->search('الوحدات')[__('الصفحات والإجراءات')];
        $this->assertContains(__('الوحدات'), $shortcuts);
        $this->assertSame(__('إضافة :label', ['label' => __('وحدة')]), $this->search('اضافه وحده')[__('الصفحات والإجراءات')][0]);
        $this->assertCount(CommandSearch::LIMIT, $this->search('جديد')[__('الصفحات والإجراءات')], 'the verb alone lists what can be added');
    }

    public function test_a_user_only_finds_what_they_may_open(): void
    {
        $this->makeRentContract(unit: $this->makeUnit(attributes: ['title' => 'شقة الياسمين']));
        $clerk = User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']);
        $clerk->givePermissionTo('clients.view_any', 'clients.view');
        $this->actingAs($clerk);

        $this->assertArrayHasKey(__('العملاء (مستأجرون ومشترون)'), $this->search('تجريبي'));
        $this->assertSame([], $this->search('الياسمين'), 'no units without permission');
        $this->assertArrayNotHasKey(__('الصفحات والإجراءات'), $this->search('اضافة وحدة'));
    }

    public function test_ctrl_k_lists_only_the_screens_the_user_may_open(): void
    {
        $clerk = User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']);
        $clerk->givePermissionTo('clients.view_any', 'clients.view');
        $this->actingAs($clerk);

        $html = $this->get("{$this->host}/admin")->assertOk()->getContent();
        $this->assertSame(1, preg_match("/rowadScreens\\(JSON\\.parse\\('(.*?)'\\)\\)/s", html_entity_decode($html), $m));
        $labels = collect(json_decode(json_decode('"'.$m[1].'"'), true))->pluck('label');
        $this->assertContains(__('العملاء (مستأجرون ومشترون)'), $labels);
        $this->assertNotContains(__('الوحدات'), $labels);
    }

    public function test_the_panel_shows_the_shortcut_and_the_menu_filter(): void
    {
        $user = User::create(['type' => 'staff', 'name' => 'مدير المكتب', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)')->get("{$this->host}/admin")->assertOk()
            ->assertSee('Ctrl K')->assertSee('rowadScreens', false)->assertSee('x-on:keydown.window.ctrl.k.prevent.stop="toggle()"', false)
            ->assertSee(__('انتقل إلى شاشة…'))->assertSee(__('ابحث في السجلات: عميل، مالك، وحدة، عقد، سند…'))
            ->assertSee('rowad-nav-filter', false)->assertSee('rowad-top-title', false)
            ->assertSee('rowad-top-user', false)->assertSee(__('مدير'));
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)')->get("{$this->host}/admin")->assertSee('⌘K');
    }
}
