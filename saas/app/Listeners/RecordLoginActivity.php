<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

/** Legacy login_activity: every sign-in attempt to an office (panels and mobile API). */
class RecordLoginActivity
{
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->record($event->user, $event->user->username ?? $event->user->email, true);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $identifier = $event->credentials['username'] ?? $event->credentials['email'] ?? null;
        $this->record($event->user instanceof User ? $event->user : null, $identifier, false);
    }

    private function record(?User $user, ?string $identifier, bool $successful): void
    {
        if (! tenancy()->initialized) {
            return; // platform administrators sign in on the central domain
        }
        if ($successful) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
        LoginActivity::create([
            'user_id' => $user?->getKey(),
            'identifier' => $identifier !== null ? mb_substr($identifier, 0, 191) : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'successful' => $successful,
        ]);
    }
}
