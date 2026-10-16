<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;

/** Rebuilds the public demo office once a visitor has changed it and left (run every few minutes). */
class RefreshDemoOffice extends Command
{
    protected $signature = 'rowad:demo:refresh';

    protected $description = 'Rebuild the public demo office if it was changed and nobody is using it';

    public function handle(): int
    {
        $demo = Demo::tenant();
        if ($demo === null || ! Demo::needsReset($demo)) {
            return self::SUCCESS;
        }
        $this->info('Demo office changed and idle for '.Demo::IDLE_MINUTES.' minutes — rebuilding');

        return $this->call('rowad:demo', ['--fresh' => true]);
    }
}
