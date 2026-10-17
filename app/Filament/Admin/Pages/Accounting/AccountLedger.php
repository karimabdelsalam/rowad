<?php

namespace App\Filament\Admin\Pages\Accounting;

use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Models\Account;
use App\Services\Accounting\Statements;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** كشف حساب (دفتر الأستاذ): one account's movements with a running balance; a heading shows its sub-accounts too. */
class AccountLedger extends AccountingPage
{
    protected static string $ability = 'ledger';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.admin.accounting.ledger';

    #[Url]
    public ?int $account = null;

    public static function getNavigationLabel(): string
    {
        return __('كشف حساب');
    }

    public function mount(): void
    {
        parent::mount();
        $this->account ??= Account::query()->where('system_key', 'cash')->value('id');
    }

    public function getTitle(): string
    {
        return ($a = $this->selected()) ? __('كشف حساب: :name', ['name' => $a->label()]) : __('كشف حساب');
    }

    public function selected(): ?Account
    {
        return $this->account ? Account::find($this->account) : null;
    }

    /** @return array<int, string> */
    public function accountOptions(): array
    {
        return Account::query()->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all();
    }

    public function ledger(): ?array
    {
        return ($a = $this->selected()) ? app(Statements::class)->ledger($a, $this->from, $this->until) : null;
    }

    public function entryUrl(int $id): ?string
    {
        return JournalEntryResource::canViewAny()
            ? JournalEntryResource::getUrl('view', ['record' => $id]) : null;
    }
}
