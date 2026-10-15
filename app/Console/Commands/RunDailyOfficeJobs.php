<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Scheduling\DailyOfficeJobs;
use Illuminate\Console\Command;

/** Replaces legacy cron/daily.php, for every active office. */
class RunDailyOfficeJobs extends Command
{
    protected $signature = 'rowad:daily {--tenant=* : Limit to these office ids}';

    protected $description = 'Reminders, expiry alerts, contract refresh and management fees for every office';

    public function handle(): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))->get()
            ->filter(fn (Tenant $t) => $t->isAccessible());

        foreach ($tenants as $tenant) {
            try {
                $result = $tenant->run(fn () => app(DailyOfficeJobs::class)->run());
                $this->line($tenant->name.': '.json_encode($result));
            } catch (\Throwable $e) {
                report($e);
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
