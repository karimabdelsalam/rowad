<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Replaces legacy cron/hourly.php usage counters (total_units, total_file_size) for plan quotas. */
class RecordOfficeUsage extends Command
{
    protected $signature = 'rowad:usage';

    protected $description = 'Record units, users and storage used by each office';

    public function handle(): int
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $usage = $tenant->run(fn () => [
                'units' => Unit::count(),
                'users' => User::where('type', 'staff')->count(),
                'storage_mb' => round(collect(File::allFiles(storage_path('app')))->sum(fn ($f) => $f->getSize()) / 1048576, 1),
            ]);
            $tenant->forceFill(['usage' => $usage + ['recorded_at' => now()->toIso8601String()]])->save();
        });

        return self::SUCCESS;
    }
}
