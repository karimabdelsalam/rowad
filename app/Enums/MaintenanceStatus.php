<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** A maintenance request from the tenant's report to the technician's visit and the bill. */
enum MaintenanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case AwaitingApproval = 'awaiting_approval';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Still needs the office's attention. */
    public const OPEN = ['new', 'awaiting_approval', 'scheduled', 'in_progress'];

    public function isOpen(): bool
    {
        return in_array($this->value, self::OPEN, true);
    }

    public function getLabel(): string
    {
        return __(match ($this) {
            self::New => 'جديد',
            self::AwaitingApproval => 'بانتظار موافقة المالك',
            self::Scheduled => 'تم تحديد موعد',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'تم الإنجاز',
            self::Cancelled => 'ملغي',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::AwaitingApproval => 'danger',
            self::Scheduled => 'info',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::New => Heroicon::OutlinedInboxArrowDown,
            self::AwaitingApproval => Heroicon::OutlinedHandRaised,
            self::Scheduled => Heroicon::OutlinedCalendarDays,
            self::InProgress => Heroicon::OutlinedWrench,
            self::Completed => Heroicon::OutlinedCheckBadge,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
