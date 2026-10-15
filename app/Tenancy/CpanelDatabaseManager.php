<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Illuminate\Support\Facades\Process;

/**
 * Office databases on cPanel hosting. cPanel MySQL users cannot run CREATE DATABASE, so databases are
 * created through cPanel's UAPI (Mysql::create_database) with an API token, then the app's database
 * user is given access to them. Queries still go through the normal MySQL connection.
 */
class CpanelDatabaseManager extends MySQLDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $name = $tenant->database()->getName();
        $this->uapi('create_database', ['name' => $name]);
        $this->uapi('set_privileges_on_database', [
            'user' => config("database.connections.{$this->connection}.username"),
            'database' => $name,
            'privileges' => 'ALL PRIVILEGES',
        ]);

        return true;
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $this->uapi('delete_database', ['name' => $tenant->database()->getName()]);

        return true;
    }

    /** @return mixed the call's data */
    private function uapi(string $function, array $params): mixed
    {
        $config = config('rowad.cpanel');

        if (($config['driver'] ?? 'http') === 'cli') {
            return $this->uapiCli($function, $params);
        }

        if (blank($config['user']) || blank($config['token'])) {
            throw new RuntimeException('cPanel database manager needs CPANEL_USER and CPANEL_API_TOKEN.');
        }

        $response = Http::withHeaders(['Authorization' => "cpanel {$config['user']}:{$config['token']}"])
            ->withOptions(['verify' => (bool) $config['verify_ssl']])
            ->timeout(60)
            ->get("https://{$config['host']}:{$config['port']}/execute/Mysql/{$function}", $params);

        if (! $response->successful() || (int) $response->json('status') !== 1) {
            $errors = implode(' ', (array) ($response->json('errors') ?? [])) ?: 'HTTP '.$response->status();

            throw new RuntimeException("cPanel Mysql::{$function} failed: {$errors}");
        }

        return $response->json('data');
    }

    /** Hosts that disable API tokens: the uapi CLI runs as the account user and needs no token. */
    private function uapiCli(string $function, array $params): mixed
    {
        return self::cli('Mysql', $function, $params);
    }

    /** Runs `uapi --output=json <Module> <function> key=value...` and returns its data (also used by rowad:doctor). */
    public static function cli(string $module, string $function, array $params = []): mixed
    {
        $command = [config('rowad.cpanel.cli_binary'), '--output=json', $module, $function];
        foreach ($params as $key => $value) {
            $command[] = "{$key}={$value}";
        }

        $process = Process::timeout(60)->run($command);
        $result = json_decode($process->output(), true)['result'] ?? null;
        if (! $process->successful() || (int) ($result['status'] ?? 0) !== 1) {
            $errors = implode(' ', (array) ($result['errors'] ?? []))
                ?: trim($process->errorOutput().' '.$process->output());

            throw new RuntimeException("cPanel {$module}::{$function} failed: {$errors}");
        }

        return $result['data'] ?? null;
    }
}
