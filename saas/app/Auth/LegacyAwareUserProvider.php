<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Users imported from the old system only have an unsalted MD5. On their first
 * successful login the password is re-hashed with the current algorithm and the
 * MD5 is discarded, so nobody has to reset their password after the move.
 */
class LegacyAwareUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');
        $legacy = $user->getAttribute('legacy_password');

        if (filled($legacy) && $plain !== '' && hash_equals(strtolower($legacy), md5($plain))) {
            $user->forceFill(['password' => $plain, 'legacy_password' => null])->save();

            return true;
        }

        return parent::validateCredentials($user, $credentials);
    }
}
