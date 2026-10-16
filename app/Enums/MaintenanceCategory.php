<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MaintenanceCategory: string implements HasIcon, HasLabel
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case AirConditioning = 'ac';
    case Carpentry = 'carpentry';
    case Painting = 'painting';
    case Appliances = 'appliances';
    case PestControl = 'pest_control';
    case Cleaning = 'cleaning';
    case Other = 'other';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Plumbing => 'سباكة',
            self::Electrical => 'كهرباء',
            self::AirConditioning => 'تكييف',
            self::Carpentry => 'نجارة وأبواب',
            self::Painting => 'دهانات وجدران',
            self::Appliances => 'أجهزة منزلية',
            self::PestControl => 'مكافحة حشرات',
            self::Cleaning => 'نظافة',
            self::Other => 'أخرى',
        });
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Plumbing => Heroicon::OutlinedBeaker,
            self::Electrical => Heroicon::OutlinedBolt,
            self::AirConditioning => Heroicon::OutlinedSun,
            self::Carpentry => Heroicon::OutlinedHome,
            self::Painting => Heroicon::OutlinedPaintBrush,
            self::Appliances => Heroicon::OutlinedCpuChip,
            self::PestControl => Heroicon::OutlinedBugAnt,
            self::Cleaning => Heroicon::OutlinedSparkles,
            self::Other => Heroicon::OutlinedWrenchScrewdriver,
        };
    }
}
