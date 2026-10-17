<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** The five account classes; assets and expenses grow on the debit side, the others on the credit side. */
enum AccountType: string implements HasColor, HasLabel
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Asset => 'أصول',
            self::Liability => 'خصوم',
            self::Equity => 'حقوق ملكية',
            self::Revenue => 'إيرادات',
            self::Expense => 'مصروفات',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Asset => 'info',
            self::Liability => 'warning',
            self::Equity => 'gray',
            self::Revenue => 'success',
            self::Expense => 'danger',
        };
    }

    public function isDebitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }

    /** First digit of the account code. */
    public function codePrefix(): string
    {
        return (string) (array_search($this, self::cases(), true) + 1);
    }
}
