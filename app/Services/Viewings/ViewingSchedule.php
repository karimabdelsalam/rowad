<?php

namespace App\Services\Viewings;

use App\Enums\NotificationEvent as E;
use App\Enums\ViewingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\Unit;
use App\Models\User;
use App\Models\Viewing;
use App\Services\Messaging\Notifier;
use App\Support\Maps;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Viewing appointments for listed units: the office's visiting hours become bookable slots on its website,
 * one visitor per unit per slot. The visitor gets a confirmation and a same-day reminder; staff are alerted,
 * mark the outcome and can turn the visitor into a client.
 */
class ViewingSchedule
{
    public const DAYS_AHEAD = 14;

    /** Saturday … Thursday (PHP: 0 = Sunday … 6 = Saturday). */
    public const DEFAULT_DAYS = [6, 0, 1, 2, 3, 4];

    public function __construct(private readonly Notifier $notifier) {}

    public static function enabled(): bool
    {
        // on unless the office turned it off: listed units get online booking out of the box
        return Settings::get('viewing_enabled') === null || Settings::enabled('viewing_enabled');
    }

    public static function timezone(): string
    {
        return (string) (Settings::get('timezone') ?: 'Asia/Riyadh');
    }

    /** Now on the office's wall clock (what `starts_at` is stored in). */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::parse(now()->setTimezone(self::timezone())->format('Y-m-d H:i:s'));
    }

    /** @return array{days: list<int>, from: string, to: string, minutes: int, notice_hours: int} */
    public static function hours(): array
    {
        $days = Settings::list('viewing_days', array_map('strval', self::DEFAULT_DAYS));

        return [
            'days' => array_map('intval', $days ?: self::DEFAULT_DAYS),
            'from' => (string) (Settings::get('viewing_from') ?: '16:00'),
            'to' => (string) (Settings::get('viewing_to') ?: '21:00'),
            'minutes' => max(15, (int) (Settings::get('viewing_slot_minutes') ?: 30)),
            'notice_hours' => max(0, (int) (Settings::get('viewing_notice_hours') ?? 2)),
        ];
    }

    /**
     * Free slots for a unit, day by day.
     *
     * @return array<string, list<string>> 'Y-m-d' => ['16:00', '16:30', …] (days without a free slot are left out)
     */
    public function slots(Unit $unit, int $days = self::DAYS_AHEAD): array
    {
        $hours = self::hours();
        $earliest = self::now()->addHours($hours['notice_hours']);
        $taken = Viewing::query()->where('unit_id', $unit->id)->where('status', ViewingStatus::Booked)
            ->whereBetween('starts_at', [self::now()->startOfDay(), self::now()->addDays($days)->endOfDay()])
            ->pluck('starts_at')->map(fn ($at) => $at->format('Y-m-d H:i'))->flip();

        $slots = [];
        for ($d = 0; $d < $days; $d++) {
            $day = self::now()->startOfDay()->addDays($d);
            if (! in_array($day->dayOfWeek, $hours['days'], true)) {
                continue;
            }
            $at = $day->setTimeFromTimeString($hours['from']);
            $end = $day->setTimeFromTimeString($hours['to']);
            while ($at->addMinutes($hours['minutes'])->lte($end)) {
                if ($at->gte($earliest) && ! $taken->has($at->format('Y-m-d H:i'))) {
                    $slots[$day->format('Y-m-d')][] = $at->format('H:i');
                }
                $at = $at->addMinutes($hours['minutes']);
            }
        }

        return $slots;
    }

    /** @param array{starts_at: string, name: string, mobile: string, email?: ?string, notes?: ?string} $data */
    public function book(Unit $unit, array $data, string $source = 'website', ?string $ip = null): Viewing
    {
        $startsAt = CarbonImmutable::parse($data['starts_at'])->seconds(0);
        $lock = Cache::lock('viewing:'.tenant('id').':'.$unit->id.':'.$startsAt->format('YmdHi'), 10);

        $viewing = $lock->block(5, function () use ($unit, $data, $startsAt, $source, $ip) {
            if ($source === 'website' && ! in_array($startsAt->format('H:i'), $this->slots($unit)[$startsAt->format('Y-m-d')] ?? [], true)) {
                throw new BusinessRuleException(__('هذا الموعد لم يعد متاحًا، اختر موعدًا آخر'));
            }
            if ($source === 'website' && Viewing::query()->where('mobile', $data['mobile'])->upcoming()->count() >= 3) {
                throw new BusinessRuleException(__('لديك مواعيد معاينة محجوزة بالفعل؛ سيتواصل معك المكتب'));
            }

            return DB::transaction(fn () => Viewing::create([
                'unit_id' => $unit->id,
                'starts_at' => $startsAt,
                'duration_minutes' => self::hours()['minutes'],
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $source,
                'assigned_to' => $data['assigned_to'] ?? null,
                'ip_address' => $ip,
            ]));
        });

        $this->tell($viewing, E::ViewingBooked);
        if ($source === 'website') {
            $this->alertStaff($viewing);
        }

        return $viewing;
    }

    public function reschedule(Viewing $viewing, string $startsAt): Viewing
    {
        $viewing->forceFill(['starts_at' => CarbonImmutable::parse($startsAt)->seconds(0), 'reminded_at' => null])->save();
        $this->tell($viewing, E::ViewingBooked);

        return $viewing;
    }

    public function cancel(Viewing $viewing, ?string $reason = null): Viewing
    {
        $viewing->forceFill(['status' => ViewingStatus::Cancelled, 'cancel_reason' => $reason])->save();
        $this->tell($viewing, E::ViewingCancelled, ['reason' => (string) $reason]);

        return $viewing;
    }

    public function outcome(Viewing $viewing, ViewingStatus $status): Viewing
    {
        $viewing->forceFill(['status' => $status])->save();

        return $viewing;
    }

    /** The visitor became a lead worth keeping: an individual client with their name and mobile. */
    public function toClient(Viewing $viewing): Client
    {
        if ($viewing->client_id) {
            return $viewing->client;
        }
        $parts = preg_split('/\s+/u', trim($viewing->name), 2);
        $client = Client::query()->where('mobile', $viewing->mobile)->first()
            ?? Client::create(['first_name' => $parts[0], 'family_name' => $parts[1] ?? '', 'mobile' => $viewing->mobile, 'email' => $viewing->email,
                'notes' => __('من موعد معاينة :unit', ['unit' => $viewing->unit?->title])]);
        $viewing->forceFill(['client_id' => $client->id])->save();

        return $client;
    }

    /** Same-day reminders, sent once by the daily office jobs. */
    public function sendReminders(): int
    {
        $today = self::now();
        $count = 0;
        Viewing::query()->with('unit')->where('status', ViewingStatus::Booked)->whereNull('reminded_at')
            ->whereBetween('starts_at', [$today, $today->endOfDay()])
            ->each(function (Viewing $viewing) use (&$count) {
                $this->tell($viewing, E::ViewingReminder);
                $viewing->forceFill(['reminded_at' => now()])->save();
                $count++;
            });

        return $count;
    }

    private function tell(Viewing $viewing, E $event, array $extra = []): void
    {
        $unit = $viewing->unit;
        $this->notifier->send($event, $extra + [
            'unit' => $unit?->title,
            'date' => $viewing->starts_at->format('Y-m-d'),
            'time' => $viewing->starts_at->format('H:i'),
            'office_phone' => (string) (Settings::get('mobile') ?: Settings::get('phone')),
            'map_url' => $unit && filled($unit->latitude) && filled($unit->longitude) ? Maps::directionsUrl($unit->latitude, $unit->longitude) : '',
        ], [['name' => $viewing->name, 'mobile' => $viewing->mobile, 'email' => $viewing->email]]);
    }

    private function alertStaff(Viewing $viewing): void
    {
        $staff = User::query()->where('type', 'staff')->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->is_super_admin || $u->can('viewings.view_any'));
        if ($staff->isNotEmpty()) {
            FilamentNotification::make()->title(__('موعد معاينة جديد: :unit', ['unit' => $viewing->unit?->title]))
                ->body($viewing->name.' · '.$viewing->mobile.' · '.$viewing->starts_at->format('Y-m-d H:i'))
                ->icon('heroicon-o-calendar-days')->info()->sendToDatabase($staff);
        }
    }
}
