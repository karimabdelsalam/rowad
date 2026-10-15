<?php

use Illuminate\Support\Facades\Schedule;

// Legacy cron/daily.php, cron/hourly.php; messages are delivered by the queue worker (legacy cron/perminute.php).
Schedule::command('rowad:daily')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:subscriptions')->dailyAt('02:45')->withoutOverlapping()->onOneServer();
Schedule::command('rowad:usage')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
