<?php

namespace App\Console\Commands;

use App\Services\Platform\SubscriptionManager;
use Illuminate\Console\Command;

/** Replaces the legacy updatecore expiry check: past due after the paid period, suspended after the grace days, with reminders. */
class CheckSubscriptions extends Command
{
    protected $signature = 'rowad:subscriptions';

    protected $description = 'Move expired offices to past due / suspended and send renewal reminders';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $done = $subscriptions->checkExpirations();
        $this->info(sprintf('past due: %d, suspended: %d, reminders: %d', $done['past_due'], $done['suspended'], $done['reminders']));

        return self::SUCCESS;
    }
}
