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

        // Answer the pinger at once (they usually give up after ~30 s) and run the scheduler after the
        // response is sent; working through the queue can take most of a minute.
        ignore_user_abort(true);
        app()->terminating(fn () => Artisan::call('schedule:run'));

        return response('ok');
    }
}
