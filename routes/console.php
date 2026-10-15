<?php

use Illuminate\Support\Facades\Schedule;

// Legacy cron/daily.php, cron/hourly.php; messages are delivered by the queue worker (legacy cron/perminute.php).
Schedule::command('rowad:daily')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:subscriptions')->dailyAt('02:45')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:usage')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
// Heartbeat for `rowad:doctor`: proves the per-minute cron is running.
Schedule::call(fn () => touch(storage_path(\App\Console\Commands\Doctor::HEARTBEAT)))->everyMinute()->name('heartbeat');
// The public demo office starts clean every day (DEMO_RESET_DAILY=true).
Schedule::command('rowad:demo --fresh')->dailyAt('04:00')->when(fn () => config('rowad.demo_reset_daily'))->withoutOverlapping()->onOneServer();

// cPanel and other hosting without Supervisor: work through queued messages every minute from cron (QUEUE_WORKER=scheduler).
if (config('rowad.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
}
