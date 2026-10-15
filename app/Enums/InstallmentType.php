<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Legacy payments.type values, renamed consistently. */
enum InstallmentType: string implements HasLabel
{
    case DayRent = 'day_rent';
    case MonthRent = 'month_rent';
    case YearRentMonthly = 'year_rent_month';
    case YearRentQuarterly = 'year_rent_quarter';
    case YearRentHalfYearly = 'year_rent_half_year';
    case YearRentYearly = 'year_rent_year';
    case InstantSale = 'instant_sale';
    case PartsSale = 'parts_sale';
    case InstallmentSale = 'installment_sale';
    case Expenses = 'expenses';

    public static function fromLegacy(string $value): self
    {
        return match ($value) {
            'year_rent_quartyear' => self::YearRentQuarterly,
            'year_rent_midyear' => self::YearRentHalfYearly,
            'instant_sell' => self::InstantSale,
            'parts_sell' => self::PartsSale,
            'installs_sell' => self::InstallmentSale,
            default => self::from($value),
        };
    }

    public function isRent(): bool
    {
        return str_contains($this->value, 'rent');
    }

    public function getLabel(): string
    {
        return __(match ($this) {
            self::DayRent => 'إيجار يومي',
            self::MonthRent => 'إيجار شهري',
            self::YearRentMonthly => 'إيجار سنوي - دفعة شهرية',
            self::YearRentQuarterly => 'إيجار سنوي - دفعة ربع سنوية',
            self::YearRentHalfYearly => 'إيجار سنوي - دفعة نصف سنوية',
            self::YearRentYearly => 'إيجار سنوي - دفعة سنوية',
            self::InstantSale => 'بيع - دفعة واحدة',
            self::PartsSale => 'بيع - دفعات',
            self::InstallmentSale => 'بيع - أقساط',
            self::Expenses => 'مصروفات على العقد',
        });
    }
}
