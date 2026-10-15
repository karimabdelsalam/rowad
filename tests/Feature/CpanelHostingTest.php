<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\CpanelDatabaseManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** cPanel hosting: office databases through cPanel's API, queue work from cron. */
class CpanelHostingTest extends TestCase
{
    use RefreshDatabase;

    private function office(): Tenant
    {
        $tenant = new Tenant(['id' => 'abc']);
        $tenant->setInternal('db_name', 'aqarnova_tabc');

        return $tenant;
    }

    private function manager(): CpanelDatabaseManager
    {
        config(['rowad.cpanel' => ['host' => 'localhost', 'port' => 2083, 'user' => 'aqarnova', 'token' => 'TOKEN123', 'verify_ssl' => false]]);
        $manager = new CpanelDatabaseManager;
        $manager->setConnection(config('tenancy.database.central_connection'));

        return $manager;
    }

    public function test_office_database_is_created_and_granted_through_the_cpanel_api(): void
    {
        config(['database.connections.'.config('tenancy.database.central_connection').'.username' => 'aqarnova_app']);
        Http::fake(['localhost:2083/*' => Http::response(['status' => 1, 'errors' => null, 'data' => null])]);

        $this->assertTrue($this->manager()->createDatabase($this->office()));

        Http::assertSentInOrder([
            fn (Request $r) => str_contains($r->url(), '/execute/Mysql/create_database') && $r['name'] === 'aqarnova_tabc'
                && $r->header('Authorization')[0] === 'cpanel aqarnova:TOKEN123',
            fn (Request $r) => str_contains($r->url(), '/execute/Mysql/set_privileges_on_database')
                && $r['database'] === 'aqarnova_tabc' && $r['user'] === 'aqarnova_app' && $r['privileges'] === 'ALL PRIVILEGES',
        ]);
    }

    public function test_cpanel_errors_stop_the_signup_with_the_reason(): void
    {
        Http::fake(['localhost:2083/*' => Http::response(['status' => 0, 'errors' => ['You have reached your database quota.']])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database quota');
        $this->manager()->createDatabase($this->office());
    }

    public function test_deleting_an_office_deletes_its_database_through_the_api(): void
    {
        Http::fake(['localhost:2083/*' => Http::response(['status' => 1])]);

        $this->manager()->deleteDatabase($this->office());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/execute/Mysql/delete_database') && $r['name'] === 'aqarnova_tabc');
    }

    public function test_queue_runs_from_cron_when_there_is_no_supervisor(): void
    {
        $commands = fn () => collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode("\n");
        $this->assertStringNotContainsString('queue:work', $commands());

        config(['rowad.queue_via_scheduler' => true]);
        require base_path('routes/console.php');
        $this->assertStringContainsString('queue:work --stop-when-empty', $commands());
    }

    public function test_a_subdomain_no_office_owns_shows_a_friendly_404(): void
    {
        $this->get('http://nobody.localhost/')->assertNotFound()
            ->assertSee('لا توجد مؤسسة بهذا الرابط')->assertSee('http://localhost/signup', false);
        $this->getJson('http://nobody.localhost/api/v1/units')->assertNotFound();
    }

    public function test_cli_driver_creates_the_database_without_an_api_token(): void
    {
        config(['rowad.cpanel' => ['driver' => 'cli', 'cli_binary' => '/usr/local/cpanel/bin/uapi', 'host' => 'localhost', 'port' => 2083,
            'user' => 'aqarnova', 'token' => null, 'verify_ssl' => false]]);
        config(['database.connections.'.config('tenancy.database.central_connection').'.username' => 'aqarnova_app']);
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result(json_encode(['result' => ['status' => 1, 'data' => null]]))]);
        Http::fake();

        $manager = new CpanelDatabaseManager;
        $manager->setConnection(config('tenancy.database.central_connection'));
        $this->assertTrue($manager->createDatabase($this->office()));

        \Illuminate\Support\Facades\Process::assertRan(fn ($p) => $p->command === ['/usr/local/cpanel/bin/uapi', '--output=json', 'Mysql', 'create_database', 'name=aqarnova_tabc']);
        \Illuminate\Support\Facades\Process::assertRan(fn ($p) => in_array('user=aqarnova_app', (array) $p->command, true));
        Http::assertNothingSent();
    }

    public function test_cli_driver_errors_carry_the_reason(): void
    {
        config(['rowad.cpanel.driver' => 'cli', 'rowad.cpanel.cli_binary' => 'uapi']);
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result(json_encode(['result' => ['status' => 0, 'errors' => ['quota exceeded']]]))]);

        $this->expectExceptionMessage('quota exceeded');
        CpanelDatabaseManager::cli('Mysql', 'create_database', ['name' => 'x']);
    }

    public function test_external_cron_link_needs_the_secret_and_runs_the_scheduler_after_answering(): void
    {
        $this->get('http://localhost/system-cron/anything')->assertNotFound(); // no CRON_TOKEN set: link disabled

        config(['rowad.cron_token' => 'long-secret-token']);
        $this->get('http://localhost/system-cron/wrong')->assertNotFound();

        \Illuminate\Support\Facades\Artisan::shouldReceive('call')->once()->with('schedule:run');
        $this->get('http://localhost/system-cron/long-secret-token')->assertOk()->assertSee('ok');
    }
}
