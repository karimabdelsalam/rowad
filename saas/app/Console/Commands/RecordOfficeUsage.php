<?php

namespace App\Console\Commands;

use App\Models\AiMessage;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Replaces legacy cron/hourly.php usage counters (total_units, total_file_size) for plan quotas, plus AI use this month. */
class RecordOfficeUsage extends Command
{
    protected $signature = 'rowad:usage';

    protected $description = 'Record units, users, storage and AI use of each office';

    public function handle(): int
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $usage = $tenant->run(fn () => [
                'units' => Unit::count(),
                'users' => User::where('type', 'staff')->count(),
                // a new office has no storage folder until its first upload
                'storage_mb' => is_dir(storage_path('app')) ? round(collect(File::allFiles(storage_path('app')))->sum(fn ($f) => $f->getSize()) / 1048576, 1) : 0.0,
                // AI this month: answers count against the plan; tokens are what the platform pays for
                ...array_map('intval', (array) AiMessage::query()->toBase()->where('role', 'assistant')->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('count(*) as ai_messages, coalesce(sum(input_tokens), 0) as ai_input_tokens, coalesce(sum(output_tokens), 0) as ai_output_tokens')
                    ->first()),
            ]);
            $tenant->forceFill(['usage' => $usage + ['recorded_at' => now()->toIso8601String()]])->save();
        });

        return self::SUCCESS;
    }
}
