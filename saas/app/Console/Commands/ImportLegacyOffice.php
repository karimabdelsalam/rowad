<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves an existing office from the legacy PHP system into its SaaS tenant:
 *   php artisan rowad:import-legacy acme --database=rowad_acme --username=root --media=/var/www/old/media
 * The legacy password is read from LEGACY_DB_PASSWORD or prompted for, so it never lands in shell history.
 */
class ImportLegacyOffice extends Command
{
    protected $signature = 'rowad:import-legacy
        {tenant : Office id or domain}
        {--driver=mysql : Legacy database driver}
        {--host=127.0.0.1} {--port=3306}
        {--database= : Legacy database name (or SQLite file path)}
        {--username=root}
        {--prefix= : Legacy table prefix, if any}
        {--media= : Path to the legacy "media" upload folder to copy attachments from}
        {--legacy-templates : Print with the office\'s old templates instead of the modern designs}
        {--force : Import even if the office already has units}';

    protected $description = 'Import an office\'s data from the legacy Rowad database';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'))
            ?? config('tenancy.domain_model')::where('domain', $this->argument('tenant'))->first()?->tenant;
        if ($tenant === null) {
            $this->error('Office not found.');

            return self::FAILURE;
        }
        if (blank($this->option('database'))) {
            $this->error('--database is required.');

            return self::FAILURE;
        }
        $media = $this->option('media');
        if ($media !== null && ! is_dir($media)) {
            $this->error("Media folder {$media} does not exist.");

            return self::FAILURE;
        }

        $driver = $this->option('driver');
        config(['database.connections.legacy' => $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => $this->option('database'), 'prefix' => (string) $this->option('prefix'), 'foreign_key_constraints' => false]
            : [
                'driver' => $driver, 'host' => $this->option('host'), 'port' => $this->option('port'),
                'database' => $this->option('database'), 'username' => $this->option('username'),
                'password' => config('rowad.legacy_db_password') ?? $this->secret('Legacy database password') ?? '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => (string) $this->option('prefix'), 'strict' => false,
            ]]);
        DB::purge('legacy');
        $legacy = DB::connection('legacy');

        if (! $this->option('force') && $tenant->run(fn () => Unit::withTrashed()->exists())) {
            $this->error('This office already has units. Re-run with --force to import anyway.');

            return self::FAILURE;
        }

        $this->info("Importing into {$tenant->name} ({$tenant->id})…");
        $result = $tenant->run(fn () => app(LegacyImporter::class)->import($legacy, $media, (bool) $this->option('legacy-templates')));

        $this->table(['Legacy table', 'Rows imported'], collect($result['counts'])->map(fn ($n, $t) => [$t, $n])->values()->all());
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }
        $this->info(sprintf('Done with %d warning(s). Legacy users keep their old passwords until their first login.', count($result['warnings'])));

        return self::SUCCESS;
    }
}
