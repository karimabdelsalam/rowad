<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\GeneralSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Installments\InstallmentCollector;
use App\Services\Portal\PortalAccounts;
use App\Services\Reports\DashboardStats;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class DashboardsAndSettingsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-15');
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function portfolio(): array
    {
        $rented = $this->makeUnit();
        $this->makeUnit(); // vacant
        $client = $this->makeClient();
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $rented->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => 1000,
        ], [$client->id => 100]);
        // Jan collected; Feb overdue; Mar due this month.
        app(InstallmentCollector::class)->collect($contract->installments()->orderBy('due_date')->first());

        return compact('rented', 'client', 'contract');
    }

    public function test_office_figures(): void
    {
        $this->portfolio();
        $s = app(DashboardStats::class)->office();

        $this->assertSame([2, 1, 50.0], [$s['units'], $s['rented'], $s['occupancy']]);
        $this->assertSame(2000.0, $s['overdue']);          // Feb + Mar 1st (due date already passed on Mar 15)
        $this->assertSame(1000.0, $s['due_this_month']);
        $this->assertSame(1000.0, $s['collected_this_month']);
        $this->assertSame(1000.0, $s['owners_payable']);

        $chart = app(DashboardStats::class)->monthlyCollections(2026);
        $this->assertSame(1000.0, $chart['collected'][2]);
        $this->assertSame(1000.0, $chart['due'][0]);
    }

    public function test_all_dashboards_render(): void
    {
        $p = $this->portfolio();
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get("{$this->host}/admin")->assertOk()->assertSee('نسبة الإشغال')->assertSee('50 %');

        $this->flushSession();
        $owner = app(PortalAccounts::class)->issue($p['rented']->owners()->first(), 'o@office.test', 'secret-pass-1')['user'];
        $this->actingAs($owner)->get("{$this->host}/owner")->assertOk()->assertSee('1,000.00');

        $this->flushSession();
        $client = app(PortalAccounts::class)->issue($p['client'], 'c@office.test', 'secret-pass-1')['user'];
        $this->actingAs($client)->get("{$this->host}/client")->assertOk()->assertSee('الدفعة القادمة')->assertSee('2026-02-01');
    }

    public function test_general_settings_are_saved_and_secrets_encrypted(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'site_name' => 'مكتب السلام', 'vat_number' => '310122393500003', 'vat_percent' => 15,
                'income_tax_enabled' => true, 'income_tax_percent' => 5,
                'sms_provider' => 'unifonic', 'sms_password' => 'top-secret', 'locale' => 'ar', 'system_calendar' => 'hijri',
                'salary_calendar' => 'gregorian', 'timezone' => 'Asia/Riyadh', 'currency' => 'SAR', 'mail_driver' => 'platform',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('مكتب السلام', Settings::get('site_name'));
        $this->assertTrue(Settings::enabled('income_tax_enabled'));
        $this->assertSame('hijri', Settings::get('system_calendar'));
        $this->assertSame('top-secret', Settings::get('sms_password'));
        $this->assertNotSame('top-secret', Setting::where('key', 'sms_password')->value('value'));
    }

    public function test_invalid_vat_number_is_rejected(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(GeneralSettings::class)
            ->fillForm(['vat_number' => '12345'])
            ->call('save')
            ->assertHasFormErrors(['vat_number']);
    }

    public function test_my_tasks_and_notes_from_the_dashboard(): void
    {
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $other = User::create(['name' => 'آخر', 'email' => 'other@office.test', 'password' => 'secret-password']);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel('admin');
        $mine = \App\Models\Task::create(['created_by' => $other->id, 'assigned_to' => $admin->id, 'content' => 'مهمتي']);
        $theirs = \App\Models\Task::create(['created_by' => $admin->id, 'assigned_to' => $other->id, 'content' => 'مهمة غيري']);
        \App\Models\Note::create(['user_id' => $other->id, 'content' => 'ملاحظة غيري']);

        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\MyTasks::class)
            ->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs])
            ->callTableAction('complete', $mine);
        $this->assertNotNull($mine->fresh()->completed_at);

        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\PersonalNotes::class)
            ->assertCanNotSeeTableRecords(\App\Models\Note::all())
            ->callTableAction('addNote', data: ['content' => 'اتصل بالمالك']);
        $this->assertSame(['اتصل بالمالك'], \App\Models\Note::where('user_id', $admin->id)->pluck('content')->all());

        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\PortalLinks::class)->assertSee(url('/owner'))->assertSee(url('/client'))->assertSee('data:image/svg+xml', false);
        \Livewire\Livewire::test(\App\Filament\Widgets\LoginActivities::class)->callTableAction('clear');
    }
}
