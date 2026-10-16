<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Leave kinds of the Saudi Labour Law (articles 109–117) plus unpaid leave. */
enum LeaveType: string implements HasColor, HasLabel
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Maternity = 'maternity';
    case Paternity = 'paternity';
    case Marriage = 'marriage';
    case Bereavement = 'bereavement';
    case Hajj = 'hajj';
    case Exam = 'exam';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Annual => 'سنوية',
            self::Sick => 'مرضية',
            self::Unpaid => 'بدون راتب',
            self::Maternity => 'وضع (أمومة)',
            self::Paternity => 'مولود',
            self::Marriage => 'زواج',
            self::Bereavement => 'وفاة قريب',
            self::Hajj => 'حج',
            self::Exam => 'امتحانات',
            self::Other => 'أخرى',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Annual => 'primary',
            self::Sick => 'warning',
            self::Unpaid => 'danger',
            default => 'gray',
        };
    }

    /** Fully paid days granted by law (null: see LeaveBalance for the annual/sick scales). */
    public function statutoryDays(): ?int
    {
        return match ($this) {
            self::Maternity => 84,   // 12 weeks (art. 151, 2024 amendment)
            self::Paternity => 3,
            self::Marriage => 5,
            self::Bereavement => 5,
            self::Hajj => 10,
            default => null,
        };
    }

    public function isPaid(): bool
    {
        return $this !== self::Unpaid;
    }
}
