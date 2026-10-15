<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy maintenance backup: a gzip'd SQL file of the office's own data (INSERT statements per table),
 * restorable into the same schema. Platform-wide backups of every office are handled by the host.
 */
final class OfficeBackup
{
    /** Volatile tables that are not worth keeping. */
    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];

    /** @return array<string, int> table => rows written */
    public function write(string $path): array
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $grammar = $connection->getQueryGrammar();
        $gz = gzopen($path, 'wb6');
        $counts = [];

        gzwrite($gz, '-- Rowad office backup '.now()->toIso8601String().' — office '.tenant('id')."\n");
        foreach (collect(Schema::getTableListing(schemaQualified: false))->diff(self::SKIP)->sort() as $table) {
            $counts[$table] = 0;
            gzwrite($gz, "\n-- {$table}\nDELETE FROM ".$grammar->wrapTable($table).";\n");
            $columns = null;
            foreach ($connection->table($table)->cursor() as $row) {
                $row = (array) $row;
                $columns ??= implode(', ', array_map(fn ($c) => $grammar->wrap($c), array_keys($row)));
                $values = implode(', ', array_map(fn ($v) => match (true) {
                    $v === null => 'NULL',
                    is_bool($v) => $v ? '1' : '0',
                    is_int($v), is_float($v) => (string) $v,
                    default => $pdo->quote((string) $v),
                }, $row));
                gzwrite($gz, 'INSERT INTO '.$grammar->wrapTable($table)." ({$columns}) VALUES ({$values});\n");
                $counts[$table]++;
            }
        }
        gzclose($gz);

        return $counts;
    }
}
