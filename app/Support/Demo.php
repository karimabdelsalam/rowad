<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * The public demo office is shared by every visitor, so it is fenced and self-cleaning:
 *   - actions that reach outside the office (cancelling the subscription, payment/SMS/mail keys, ZATCA
 *     onboarding, deleting the shared accounts) are blocked, and messages are never sent;
 *   - any change marks it "changed"; once nobody has used it for a while, it is rebuilt from scratch, so
 *     the next visitor finds it as new. Both timestamps live on the demo tenant (central database), which
 *     the rebuild replaces.
 */
final class Demo
{
    /** Minutes without any visit before a changed demo is rebuilt. */
    public const IDLE_MINUTES = 20;

    /** Assistant questions per visitor (IP) per hour in the demo. */
    public const AI_PER_HOUR = 10;

    /** Settings that connect the office to outside services; a visitor's values would reach real providers. */
    public const LOCKED_SETTING_GROUPS = ['mail', 'sms', 'payments', 'whatsapp'];

    /** Write each time stamp at most this often, so browsing and saving do not write on every request. */
    private const SEEN_EVERY_SECONDS = 60;

    public static function active(): bool
    {
        return tenancy()->initialized && tenant() instanceof Tenant && tenant()->isDemo();
    }

    /** Someone changed data in the demo (called from model events). */
    public static function changed(): void
    {
        if (self::active()) {
            self::stamp('demo_changed_at', self::SEEN_EVERY_SECONDS);
        }
    }

    /** A visitor is using the demo (called on each panel request). */
    public static function seen(): void
    {
        if (self::active()) {
            self::stamp('demo_seen_at', self::SEEN_EVERY_SECONDS);
        }
    }

    /** Changed since it was built, and nobody has used it for IDLE_MINUTES. */
    public static function needsReset(Tenant $demo, ?int $now = null): bool
    {
        $now ??= now()->getTimestamp();
        $changed = (int) ($demo->getAttribute('demo_changed_at') ?? 0);
        $seen = (int) ($demo->getAttribute('demo_seen_at') ?? 0);

        return $changed > 0 && max($changed, $seen) <= $now - self::IDLE_MINUTES * 60;
    }

    /** @return Tenant|null the demo office, if this platform has one */
    public static function tenant(): ?Tenant
    {
        return Tenant::query()->whereHas('domains', fn ($q) => $q->where('domain', 'demo.'.config('rowad.tenancy.base_domain')))
            ->get()->first(fn (Tenant $t) => $t->isDemo());
    }

    private static function stamp(string $key, int $minGap): void
    {
        $tenant = tenant();
        $now = now()->getTimestamp();
        if ($minGap > 0 && $now - (int) ($tenant->getAttribute($key) ?? 0) < $minGap) {
            return;
        }
        $tenant->setAttribute($key, $now);
        $tenant->saveQuietly();
    }
}
