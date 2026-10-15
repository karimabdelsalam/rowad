<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlCalendar;
use IntlDateFormatter;
use InvalidArgumentException;

/**
 * Umm al-Qura (Saudi official) Hijri calendar backed by ICU, replacing the
 * legacy uCal class (g2u / u2g / firstMonthDay).
 */
final class Hijri
{
    private const LOCALE = 'ar_SA@calendar=islamic-umalqura';

    /** @return array{0:int,1:int,2:int} [year, month (1-12), day] */
    public static function fromGregorian(DateTimeInterface|string $date): array
    {
        $calendar = self::calendarAt(self::toDate($date));

        return [
            $calendar->get(IntlCalendar::FIELD_YEAR),
            $calendar->get(IntlCalendar::FIELD_MONTH) + 1,
            $calendar->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }

    public static function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        $calendar = self::calendar();
        $calendar->clear();
        $calendar->set($year, $month - 1, 1, 12, 0, 0);

        $length = $calendar->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH);
        if ($day < 1 || $day > 30) {
            throw new InvalidArgumentException("Invalid Hijri day {$day}");
        }
        $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, min($day, $length));

        return CarbonImmutable::createFromTimestampMs($calendar->getTime(), 'UTC')->startOfDay();
    }

    /** Parse "1448/05/04" or "1448-05-04" (legacy form input) into a Gregorian date. */
    public static function parse(string $hijri): CarbonImmutable
    {
        if (! preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', trim($hijri), $m)) {
            throw new InvalidArgumentException("Invalid Hijri date [{$hijri}]");
        }

        return self::toGregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * Add Hijri months keeping the day of month (legacy uCal::firstMonthDay).
     * When the target month is shorter the day is clamped to its last day.
     */
    public static function addMonths(DateTimeInterface|string $date, int $months): CarbonImmutable
    {
        [$year, $month, $day] = self::fromGregorian($date);
        $index = ($year * 12 + ($month - 1)) + $months;

        return self::toGregorian(intdiv($index, 12), ($index % 12) + 1, $day);
    }

    /** "1448/05/04" */
    public static function toString(DateTimeInterface|string $date): string
    {
        [$y, $m, $d] = self::fromGregorian($date);

        return sprintf('%04d/%02d/%02d', $y, $m, $d);
    }

    /** Localised long form, e.g. "٤ جمادى الأولى ١٤٤٨ هـ". */
    public static function format(DateTimeInterface|string $date, string $pattern = 'd MMMM y G', string $locale = self::LOCALE): string
    {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', IntlDateFormatter::TRADITIONAL, $pattern);

        return $formatter->format(self::calendarAt(self::toDate($date))->toDateTime());
    }

    private static function calendar(): IntlCalendar
    {
        return IntlCalendar::createInstance('UTC', self::LOCALE);
    }

    private static function calendarAt(CarbonImmutable $date): IntlCalendar
    {
        $calendar = self::calendar();
        $calendar->setTime($date->setTimezone('UTC')->setTime(12, 0)->getTimestampMs());

        return $calendar;
    }

    private static function toDate(DateTimeInterface|string $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::parse($date->format('Y-m-d'), 'UTC')
            : CarbonImmutable::parse($date, 'UTC');
    }
}
