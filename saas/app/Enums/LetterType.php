<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Legacy letters_type: each type prints with its own office template. */
enum LetterType: string implements HasLabel
{
    case Official = 'official';
    case Clearance = 'cancel';
    case RentChange = 'rentraise';
    case Handover = 'finish';
    case Evaluation = 'review';

    public function getLabel(): string
    {
        return match ($this) {
            self::Official => __('خطاب رسمي'),
            self::Clearance => __('إخلاء طرف'),
            self::RentChange => __('إشعار بتغيير الإيجار'),
            self::Handover => __('تسليم عقار'),
            self::Evaluation => __('تقييم عقار'),
        };
    }

    /** Print template key (legacy print_samples ids 7, 10, 6, 5, 11). */
    public function templateKey(): string
    {
        return match ($this) {
            self::Official => 'letter',
            self::Clearance => 'letter_cancel',
            self::RentChange => 'letter_rentraise',
            self::Handover => 'letter_finish',
            self::Evaluation => 'letter_review',
        };
    }

    /** Letters about a rented unit need its tenant and contract. */
    public function needsTenant(): bool
    {
        return in_array($this, [self::Clearance, self::RentChange, self::Handover], true);
    }
}
