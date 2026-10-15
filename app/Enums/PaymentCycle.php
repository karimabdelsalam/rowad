<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Billing cycle for rent payments, commissions and management fees.
 * Legacy values: day, month, quartyear, midyear, year, once.
 */
enum PaymentCycle: string implements HasLabel
{
    case Once = 'once';
    case Day = 'day';
    case Month = 'month';
    case Quarter = 'quarter';
    case HalfYear = 'half_year';
    case Year = 'year';

    /** Number of months per cycle (0 for once/day, which are handled specially). */
    public function months(): int
    {
        return match ($this) {
            self::Once, self::Day => 0,
            self::Month => 1,
            self::Quarter => 3,
            self::HalfYear => 6,
            self::Year => 12,
        };
    }

    public static function fromLegacy(string $value): self
    {
        return match ($value) {
            'quartyear' => self::Quarter,
            'midyear' => self::HalfYear,
            default => self::from($value),
        };
    }

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Once => 'مرة واحدة',
            self::Day => 'يومي',
            self::Month => 'شهري',
            self::Quarter => 'ربع سنوي',
            self::HalfYear => 'نصف سنوي',
            self::Year => 'سنوي',
        });
    }
}
