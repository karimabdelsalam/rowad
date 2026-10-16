<?php

use Illuminate\Database\Migrations\Migration;

/** New HR permissions (leave, payroll, advances, end of service) must exist in every office already running. */
return new class extends Migration
{
    public function up(): void
    {
        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run();
    }

    public function down(): void {}
};
