<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Symfony\Component\Process\Process;

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
        $command = [config('rowad.cpanel.cli_binary'), '--output=json', 'Mysql', $function];
        foreach ($params as $key => $value) {
            $command[] = "{$key}={$value}";
        }

        $process = new Process($command, null, null, null, 60);
        $process->run();

        $result = json_decode($process->getOutput(), true)['result'] ?? null;
        if (! $process->isSuccessful() || (int) ($result['status'] ?? 0) !== 1) {
            $errors = implode(' ', (array) ($result['errors'] ?? []))
                ?: trim($process->getErrorOutput().' '.$process->getOutput());

            throw new RuntimeException("cPanel Mysql::{$function} failed: {$errors}");
        }

        return $result['data'] ?? null;
    }
}
