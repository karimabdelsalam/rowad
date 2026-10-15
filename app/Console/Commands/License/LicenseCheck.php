<?php

namespace App\Console\Commands\License;

use App\Services\Licensing\LicenseClient;
use Illuminate\Console\Command;

/** Daily on self-hosted installations: refresh the signed token from the license server. */
class LicenseCheck extends Command
{
    protected $signature = 'rowad:license:check';

    protected $description = 'Check this installation\'s license with the license server';

    public function handle(LicenseClient $client): int
    {
        $result = $client->check();
        $state = $client->state();
        $this->line('Server: '.($result['ok'] ? 'ok' : ($result['code'] ?? 'error')).' — license state: '.$state['state']
            .($state['reason'] ? ' ('.$state['reason'].')' : ''));

        return $state['state'] === LicenseClient::INVALID ? self::FAILURE : self::SUCCESS;
    }
}
