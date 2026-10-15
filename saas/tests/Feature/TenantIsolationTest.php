<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Data and caches of one organisation never reach another. */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private ?Tenant $other = null;

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        $this->other?->delete();
        parent::tearDown();
    }

    public function test_role_changes_in_one_organisation_do_not_leak_through_the_permission_cache(): void
    {
        $this->other = $this->createTenant(['name' => 'B']);
        $first = $this->initializeTenancy(['name' => 'A']);

        Role::findByName('agent', 'web')->givePermissionTo('settings.update');
        $agentA = User::create(['name' => 'a', 'email' => 'a@a.test', 'password' => 'secret-pass-1']);
        $agentA->assignRole('agent');
        $this->assertTrue($agentA->can('settings.update'));

        tenancy()->initialize($this->other);
        $agentB = User::create(['name' => 'b', 'email' => 'b@b.test', 'password' => 'secret-pass-1']);
        $agentB->assignRole('agent');
        $this->assertFalse($agentB->fresh()->can('settings.update'), 'organisation B inherited A\'s role change');

        tenancy()->initialize($first);
        $this->assertTrue($agentA->fresh()->can('settings.update'));
    }
}
