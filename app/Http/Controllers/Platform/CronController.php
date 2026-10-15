<?php

namespace App\Http\Controllers\Platform;

use Illuminate\Support\Facades\Artisan;

/**
 * Scheduler entry point for hosts without a Cron Jobs feature: an external pinger
 * (cron-job.org, UptimeRobot, ...) hits this URL every minute instead of a server cron.
 */
class CronController
{
    public function __invoke(string $token)
    {
        abort_unless(config('rowad.cron_token') && hash_equals(config('rowad.cron_token'), $token), 404);

        Artisan::call('schedule:run');

        return response('ok');
    }
}
