<?php

namespace App\Console\Commands\License;

use App\Services\Licensing\LicenseClient;
use Illuminate\Console\Command;

class LicenseActivate extends Command
{
    protected $signature = 'rowad:license:activate {key? : License key (default LICENSE_KEY)}';

    protected $description = 'Activate this self-hosted installation with its license key';

    public function handle(LicenseClient $client): int
    {
        $result = $client->activate($this->argument('key'));
        if (! $result['ok']) {
            $this->error('Activation failed: '.($result['message'] ?? $result['code']));

            return self::FAILURE;
        }
        $payload = $client->state()['payload'] ?? [];
        $this->info('Activated for '.($payload['customer'] ?? '?').' on '.$client->domain()
            .(filled($payload['expires_at'] ?? null) ? ' until '.substr($payload['expires_at'], 0, 10) : ' (no expiry)'));

        return self::SUCCESS;
    }
}
