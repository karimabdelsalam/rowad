<?php

namespace App\Filament\Admin\Pages\Accounting;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** Base for the accounting reports: a period in the URL, permission accounting.<ability>, print. */
abstract class AccountingPage extends Page
{
    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    /** accounting.* permission in config/rowad.php */
    protected static string $ability;

    public static function getNavigationGroup(): ?string
    {
        return __('المحاسبة');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('accounting.'.static::$ability));
    }

    public function mount(): void
    {
        $this->from ??= now()->startOfYear()->toDateString();
        $this->until ??= today()->toDateString();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label(__('طباعة'))->icon(Heroicon::OutlinedPrinter)->color('gray')->alpineClickHandler('window.print()'),
        ];
    }

    /** "1 Jan 2026 – 16 Oct 2026" for the report heading. */
    public function periodLabel(): string
    {
        return __('من :from إلى :until', ['from' => $this->from ?: '—', 'until' => $this->until ?: '—']);
    }
}
