<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\Branch;

/**
 * Which branch the signed-in staff member works in. Null means "every branch": admins without a
 * branch, platform staff, portal users, queued jobs and the scheduler (no user at all).
 */
final class Branches
{
    /** @var array<string, bool> per office, per request */
    private static array $exists = [];

    public static function enabled(): bool
    {
        return tenancy()->initialized && PlanLimits::allows('branches');
    }

    /** The office has at least one branch defined (the UI stays quiet until then). */
    public static function inUse(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return self::$exists[(string) tenant('id')] ??= Branch::query()->exists();
    }

    public static function current(): ?int
    {
        if (! self::enabled()) {
            return null;
        }
        $user = auth()->user();
        if ($user === null || $user->type !== UserType::Staff || $user->is_super_admin) {
            return null;
        }

        $branch = $user->getAttributes()['branch_id'] ?? null; // users built in memory may not carry the column

        return $branch ? (int) $branch : null;
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function flush(): void
    {
        self::$exists = [];
    }
}
