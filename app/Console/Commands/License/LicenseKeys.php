<?php

namespace App\Console\Commands\License;

use App\Services\Licensing\LicenseToken;
use Illuminate\Console\Command;

/** Run once on aqarnova.com: the key pair that signs license tokens. */
class LicenseKeys extends Command
{
    protected $signature = 'rowad:license:keys';

    protected $description = 'Generate the license signing key pair (platform only)';

    public function handle(): int
    {
        if (filled(config('rowad.license.signing_key'))) {
            $this->warn('LICENSE_SIGNING_KEY is already set. Replacing it invalidates every issued token until installations check in again.');
            $this->line('Public key for installations: LICENSE_PUBLIC_KEY='.LicenseToken::publicKeyFromSecret(config('rowad.license.signing_key')));

            return self::SUCCESS;
        }
        $pair = LicenseToken::generateKeyPair();
        $this->info('Add to the platform .env (keep secret):');
        $this->line('LICENSE_SIGNING_KEY='.$pair['secret']);
        $this->newLine();
        $this->info('Give to every self-hosted installation (.env):');
        $this->line('LICENSE_PUBLIC_KEY='.$pair['public']);

        return self::SUCCESS;
    }
}
