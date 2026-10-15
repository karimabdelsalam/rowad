<?php

namespace Tests\Concerns;

use App\Models\Tenant;

trait InteractsWithTenancy
{
    protected ?Tenant $tenant = null;

    protected function createTenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::create(array_merge([
            'name' => 'مكتب الاختبار العقاري',
            'email' => 'office@example.com',
            'status' => Tenant::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
        ], $attributes));

        $tenant->domains()->create(['domain' => 'office-'.substr($tenant->id, 0, 8).'.localhost']);

        return $tenant;
    }

    protected function initializeTenancy(array $attributes = []): Tenant
    {
        $this->tenant = $this->createTenant($attributes);
        tenancy()->initialize($this->tenant);

        return $this->tenant;
    }

    protected function tearDownTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        $this->tenant?->delete();
        $this->tenant = null;
    }
}
