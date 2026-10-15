<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Owners\Pages\ListOwners;
use App\Filament\Admin\Resources\Units\Pages\ListUnits;
use App\Filament\Tables\TableExport;
use App\Models\Owner;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Legacy ?exportotf=excel / ?printotf=all on every list. */
class TableExportTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create(['name' => 'موظف', 'email' => "{$role}@office.test", 'password' => 'secret-password']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_snapshot_contains_all_filtered_rows_with_plain_values(): void
    {
        $this->actingAsRole('admin');
        foreach (range(1, 30) as $i) {
            Owner::create(['first_name' => "مالك{$i}", 'family_name' => 'الاختبار', 'full_name' => "مالك{$i} الاختبار", 'mobile' => '05000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $page = Livewire::test(ListOwners::class)->searchTable('مالك1')->instance();
        [$headings, $rows] = TableExport::snapshot($page);

        $this->assertContains(__('الاسم'), $headings);
        $this->assertCount(11, $rows); // مالك1, مالك10..19 — every page, not only the first 10
        $flat = array_merge(...$rows);
        $this->assertContains('0500000001', $flat); // phone numbers keep their leading zero
    }

    public function test_excel_download_and_print_actions(): void
    {
        $this->actingAsRole('admin');
        $this->makeUnit([[100, 100]], ['title' => 'شقة التصدير']);

        Livewire::test(ListUnits::class)->callTableAction('exportExcel')->assertFileDownloaded();
        Livewire::test(ListUnits::class)->callTableAction('printTable')->assertHasNoErrors();
    }

    public function test_units_export_requires_the_export_permission(): void
    {
        $this->actingAsRole('viewer');
        $this->assertFalse(auth()->user()->can('units.export'));

        Livewire::test(ListUnits::class)->assertTableActionHidden('exportExcel');
    }
}
