<?php

namespace Tests\Feature;

use App\Enums\NotificationEvent;
use App\Models\MessageTemplate;
use App\Models\PrintTemplate;
use App\Models\Setting;
use App\Models\Lookups\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_new_office_gets_its_own_migrated_and_seeded_database(): void
    {
        $this->initializeTenancy();

        $this->assertSame(
            ['accountant', 'admin', 'agent', 'viewer'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(Permission::count(), Role::findByName('admin')->permissions()->count());
        $this->assertTrue(Role::findByName('accountant')->hasPermissionTo('installments.collect'));
        $this->assertFalse(Role::findByName('agent')->hasPermissionTo('payment_vouchers.delete'));
        $this->assertTrue(Role::findByName('viewer')->hasPermissionTo('units.view_any'));
        $this->assertFalse(Role::findByName('viewer')->hasPermissionTo('units.delete'));

        $this->assertSame(count(NotificationEvent::cases()), MessageTemplate::count());
        $this->assertSame(12, PrintTemplate::count()); // 7 documents + 5 letter types
        $this->assertSame(15, Setting::where('key', 'vat_percent')->value('value'));
        $this->assertSame('مكتب الاختبار العقاري', Setting::where('key', 'site_name')->value('value'));
        $this->assertTrue(TransactionType::where('code', 'payment')->value('is_income'));
    }

    public function test_tenant_databases_are_isolated(): void
    {
        $first = $this->createTenant(['name' => 'Office A']);
        $second = $this->createTenant(['name' => 'Office B']);

        $first->run(fn () => \App\Models\Owner::create(['first_name' => 'مالك', 'family_name' => 'الأول']));

        $this->assertSame(1, $first->run(fn () => \App\Models\Owner::count()));
        $this->assertSame(0, $second->run(fn () => \App\Models\Owner::count()));

        $first->delete();
        $second->delete();
    }
}
