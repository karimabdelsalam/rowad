<?php

use Illuminate\Support\Facades\Schedule;

// Legacy cron/daily.php, cron/hourly.php; messages are delivered by the queue worker (legacy cron/perminute.php).
Schedule::command('rowad:daily')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:subscriptions')->dailyAt('02:45')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:usage')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();

// cPanel and other hosting without Supervisor: work through queued messages every minute from cron (QUEUE_WORKER=scheduler).
if (config('rowad.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
}
