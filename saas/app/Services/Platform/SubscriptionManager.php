<?php

namespace App\Services\Platform;

use App\Jobs\SendOutgoingMessage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * An office's paid period on the platform (legacy updatecore expiry checks):
 * recording a payment activates the office on the paid plan; when the period ends the office
 * gets a grace period as "past due", then is suspended until the next payment.
 */
class SubscriptionManager
{
    public const GRACE_DAYS = 7;

    /** Saudi VAT, included in the amount the office pays. */
    public const VAT_PERCENT = 15;

    public const STATUSES = [
        'trialing' => 'تجريبي',
        'active' => 'نشط',
        'past_due' => 'متأخر السداد',
        'expired' => 'منتهي',
        'cancelled' => 'ملغي',
    ];

    public const CYCLES = ['monthly' => 'شهري', 'yearly' => 'سنوي'];

    public static function price(Plan $plan, string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
    }

    /** A new period starts when the current one ends, so paying early never loses days. */
    public static function nextStart(Tenant $tenant): Carbon
    {
        $current = $tenant->subscriptions()->where('status', 'active')->max('ends_at');

        return $current && Carbon::parse($current)->isFuture() ? Carbon::parse($current) : now();
    }

    public function record(Tenant $tenant, Plan $plan, string $cycle, float $amount, ?Carbon $startsAt = null, ?string $reference = null): Subscription
    {
        $startsAt ??= self::nextStart($tenant);
        $endsAt = $cycle === 'yearly' ? $startsAt->copy()->addYear() : $startsAt->copy()->addMonth();

        return DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($tenant, $plan, $cycle, $amount, $startsAt, $endsAt, $reference) {
            // The trial (or a lapsed period) is over once a paid one is recorded; an active period that is extended stays as history.
            $tenant->subscriptions()->whereIn('status', ['trialing', 'past_due'])->update(['status' => 'expired']);

            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'status' => 'active',
                'amount' => $amount,
                'currency' => $plan->currency ?? 'SAR',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'gateway' => 'manual',
                'gateway_reference' => $reference,
            ]);

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'vat_amount' => round($amount * self::VAT_PERCENT / (100 + self::VAT_PERCENT), 2),
                'currency' => $subscription->currency,
                'status' => 'paid',
                'gateway' => 'manual',
                'gateway_reference' => $reference,
                'paid_at' => now(),
            ]);

            $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id, 'upgrade_request' => null])->save();

            return $subscription;
        });
    }

    /**
     * Daily: move offices whose paid period ended to "past due", suspend them after the grace period,
     * and remind them before the period (or the free trial) ends.
     *
     * @return array{past_due: int, suspended: int, reminders: int}
     */
    public function checkExpirations(): array
    {
        $done = ['past_due' => 0, 'suspended' => 0, 'reminders' => 0];

        Tenant::query()->whereIn('status', [Tenant::STATUS_ACTIVE, Tenant::STATUS_PAST_DUE])->with('plan')->each(function (Tenant $tenant) use (&$done) {
            $endsAt = $tenant->subscriptions()->whereIn('status', ['active', 'past_due'])->max('ends_at');
            if ($endsAt === null) {
                return; // offices activated by hand without a paid period are left alone
            }
            $endsAt = Carbon::parse($endsAt);

            if ($endsAt->isPast() && $tenant->status === Tenant::STATUS_ACTIVE) {
                $tenant->subscriptions()->where('status', 'active')->update(['status' => 'past_due']);
                $tenant->forceFill(['status' => Tenant::STATUS_PAST_DUE])->save();
                $done['past_due']++;
            }

            if ($endsAt->copy()->addDays(self::GRACE_DAYS)->isPast() && $tenant->status === Tenant::STATUS_PAST_DUE) {
                $tenant->subscriptions()->where('status', 'past_due')->update(['status' => 'expired']);
                $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();
                $done['suspended']++;

                return;
            }

            $daysLeft = (int) today()->diffInDays($endsAt->copy()->startOfDay(), false);
            if (in_array($daysLeft, [7, 1], true)) {
                $done['reminders'] += (int) $this->remind($tenant, __('ينتهي اشتراك :office في عقار نوفا بتاريخ :date. جدّد الاشتراك لتستمر الخدمة دون انقطاع.', [
                    'office' => $tenant->name, 'date' => $endsAt->format('Y-m-d'),
                ]));
            } elseif ($daysLeft === -1) {
                $done['reminders'] += (int) $this->remind($tenant, __('انتهى اشتراك :office في عقار نوفا. الخدمة مستمرة لمدة :days أيام سماح، ثم تتوقف حتى التجديد.', [
                    'office' => $tenant->name, 'days' => self::GRACE_DAYS,
                ]));
            }
        });

        Tenant::query()->where('status', Tenant::STATUS_TRIAL)->whereDate('trial_ends_at', today()->addDays(3))->each(function (Tenant $tenant) use (&$done) {
            $done['reminders'] += (int) $this->remind($tenant, __('تنتهي الفترة التجريبية لـ:office بتاريخ :date. اختر باقتك لتستمر بياناتك وخدماتك دون انقطاع.', [
                'office' => $tenant->name, 'date' => $tenant->trial_ends_at->format('Y-m-d'),
            ]));
        });

        return $done;
    }

    private function remind(Tenant $tenant, string $text): bool
    {
        if (blank($tenant->email)) {
            return false;
        }

        $subject = __('اشتراكك في عقار نوفا');
        $link = (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.$tenant->domains()->value('domain').'/admin/subscription';
        $body = '<p>'.e($text).'</p><p><a href="'.e($link).'">'.e(__('صفحة الاشتراك')).'</a></p>';

        try {
            Mail::html(SendOutgoingMessage::layout($subject, $body), fn ($mail) => $mail->to($tenant->email)->subject($subject));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
