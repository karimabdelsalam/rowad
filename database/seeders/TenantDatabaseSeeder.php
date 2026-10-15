<?php

namespace Database\Seeders;

use Database\Seeders\Tenant\ReferenceDataSeeder;
use Database\Seeders\Tenant\RolesAndPermissionsSeeder;
use Database\Seeders\Tenant\TemplateSeeder;
use Illuminate\Database\Seeder;

/** Runs inside a freshly created tenant database (see config/tenancy.php seeder_parameters). */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}
