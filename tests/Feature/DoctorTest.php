<?php

namespace Tests\Feature;

use App\Console\Commands\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `rowad:doctor` tells the owner what is missing on the server. */
class DoctorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path(Doctor::HEARTBEAT));
        parent::tearDown();
    }

    public function test_reports_problems_with_what_to_do_and_fails(): void
    {
        @unlink(storage_path(Doctor::HEARTBEAT));
        config(['app.debug' => true, 'mail.default' => 'log', 'rowad.tenancy.base_domain' => 'localhost']);

        $this->artisan('rowad:doctor')
            ->expectsOutputToContain('اجعلها false')
            ->expectsOutputToContain('اجعلها aqarnova.com')
            ->expectsOutputToContain('Cron Jobs')
            ->assertFailed();
    }

    public function test_cron_heartbeat_and_good_settings_pass(): void
    {
        touch(storage_path(Doctor::HEARTBEAT));
        config(['app.debug' => false, 'mail.default' => 'smtp', 'mail.from.address' => 'noreply@aqarnova.com',
            'rowad.tenancy.base_domain' => 'aqarnova.com', 'tenancy.central_domains' => ['aqarnova.com'],
            'queue.default' => 'database']);
        \App\Models\User::create(['name' => 'root', 'email' => 'root@x.test', 'password' => 'secret-password', 'is_super_admin' => true]);

        $this->artisan('rowad:doctor')
            ->expectsOutputToContain('تعمل (آخر تشغيل')
            ->doesntExpectOutputToContain('✗')
            ->assertSuccessful();
    }
}
