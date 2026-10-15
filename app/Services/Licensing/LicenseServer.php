<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Str;

/** The platform side (aqarnova.com): activates installations and issues signed tokens. */
class LicenseServer
{
    /** @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body */
    public function activate(array $input, ?string $ip): array
    {
        $license = License::where('key', Str::upper(trim((string) ($input['key'] ?? ''))))->first();
        if ($license === null) {
            return [404, ['ok' => false, 'code' => 'invalid_key', 'message' => 'License key not found.']];
        }
        if ($problem = $license->problem()) {
            return [403, ['ok' => false, 'code' => $problem, 'message' => $this->message($problem)]];
        }
        $domain = Str::lower((string) $input['domain']);
        if (! $license->allowsDomain($domain)) {
            return [403, ['ok' => false, 'code' => 'domain_not_allowed', 'message' => $this->message('domain_not_allowed')]];
        }

        $activation = $license->activations()->where('instance_id', $input['instance_id'])->first();
        if (($activation === null || $activation->status !== 'active')
            && $license->activeActivations()->count() >= $license->max_activations) {
            return [403, ['ok' => false, 'code' => 'activation_limit', 'message' => $this->message('activation_limit')]];
        }

        $activation ??= new LicenseActivation(['license_id' => $license->id, 'instance_id' => $input['instance_id']]);
        $activation->fill([
            'domain' => $domain, 'ip' => $ip, 'status' => 'active', 'deactivated_at' => null,
            'app_version' => $input['version'] ?? null, 'php_version' => $input['php'] ?? null,
            'activated_at' => $activation->activated_at ?? now(), 'last_seen_at' => now(),
        ])->save();

        return [200, ['ok' => true, 'token' => $this->token($license, $activation), 'license' => $this->summary($license)]];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    public function verify(array $input, ?string $ip): array
    {
        $license = License::where('key', Str::upper(trim((string) ($input['key'] ?? ''))))->first();
        $activation = $license?->activations()->where('instance_id', $input['instance_id'] ?? '')->first();
        if ($license === null || $activation === null || $activation->status !== 'active') {
            return [403, ['ok' => false, 'code' => 'not_activated', 'message' => $this->message('not_activated')]];
        }
        $activation->update(['last_seen_at' => now(), 'ip' => $ip, 'app_version' => $input['version'] ?? $activation->app_version]);

        // Even a refusal carries a signed token, so the installation enforces it at once and cannot ignore it.
        $problem = $license->problem() ?? (! $license->allowsDomain($activation->domain) ? 'domain_not_allowed' : null);

        return [$problem ? 403 : 200, [
            'ok' => $problem === null,
            'code' => $problem,
            'message' => $problem ? $this->message($problem) : null,
            'token' => $this->token($license, $activation, $problem),
            'license' => $this->summary($license),
        ]];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    public function deactivate(array $input): array
    {
        $license = License::where('key', Str::upper(trim((string) ($input['key'] ?? ''))))->first();
        $license?->activations()->where('instance_id', $input['instance_id'] ?? '')
            ->update(['status' => 'deactivated', 'deactivated_at' => now()]);

        return [200, ['ok' => true]];
    }

    public function token(License $license, LicenseActivation $activation, ?string $problem = null): string
    {
        $key = (string) config('rowad.license.signing_key');

        return LicenseToken::sign([
            'lic' => $license->id,
            'key_hint' => $license->maskedKey(),
            'customer' => $license->customer_name,
            'domain' => $activation->domain,
            'instance' => $activation->instance_id,
            'status' => $problem ?? 'active',
            'features' => $license->effectiveFeatures(),
            'limits' => ['offices' => $license->max_offices, 'units' => $license->max_units, 'users' => $license->max_users],
            'expires_at' => $license->expires_at?->toIso8601String(),
            'support_until' => $license->support_until?->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
            'valid_until' => now()->addDays((int) config('rowad.license.token_days'))->toIso8601String(),
        ], $key);
    }

    private function summary(License $license): array
    {
        return ['customer' => $license->customer_name, 'expires_at' => $license->expires_at?->toDateString(), 'status' => $license->status];
    }

    private function message(string $code): string
    {
        return [
            'invalid_key' => 'License key not found.',
            'revoked' => 'This license has been revoked.',
            'suspended' => 'This license is suspended. Contact Aqar Nova.',
            'expired' => 'This license has expired. Renew it with Aqar Nova.',
            'not_started' => 'This license is not active yet.',
            'domain_not_allowed' => 'This license is not valid for this domain.',
            'activation_limit' => 'This license is already active on the maximum number of servers. Deactivate one first.',
            'not_activated' => 'This installation is not activated. Run: php artisan rowad:license:activate',
        ][$code] ?? $code;
    }
}
