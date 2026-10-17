<?php

namespace App\Filament\Admin\Resources\JournalEntries;

use App\Filament\Admin\Resources\Accounts\AccountResource;
use App\Filament\Admin\Resources\BankAccounts\BankAccountResource;
use App\Filament\Admin\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Filament\Admin\Resources\JournalEntries\Pages\EditJournalEntry;
use App\Filament\Admin\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Admin\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Filament\Admin\Resources\OwnerTransactions\OwnerTransactionResource;
use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Infolists\Parts;
use App\Filament\Resource;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** القيود اليومية: entries posted from vouchers and the owner ledger, and the accountant's manual entries. */
class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'المحاسبة';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'قيد يومية';

    protected static ?string $pluralModelLabel = 'القيود اليومية';

    protected static ?string $permission = 'journal_entries';

    /** Where an automatic entry came from. */
    public const SOURCES = [
        'receipt_voucher' => ['سند قبض', ReceiptVoucherResource::class],
        'payment_voucher' => ['سند صرف', PaymentVoucherResource::class],
        'owner_transaction' => ['دفتر الملاك', OwnerTransactionResource::class],
        'bank_account' => ['رصيد افتتاحي', BankAccountResource::class],
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('بيانات القيد'))->columns(3)->schema([
                DatePicker::make('entry_date')->label(__('التاريخ'))->required()->default(today()),
                TextInput::make('description')->label(__('البيان'))->required()->maxLength(250)->columnSpan(2),
            ]),
            Section::make(__('أطراف القيد'))->schema([
                Repeater::make('lines')->hiddenLabel()
                    ->table([
                        Repeater\TableColumn::make(__('الحساب'))->width('40%'),
                        Repeater\TableColumn::make(__('مدين')),
                        Repeater\TableColumn::make(__('دائن')),
                        Repeater\TableColumn::make(__('البيان')),
                    ])
                    ->schema([
                        Select::make('account_id')->options(fn () => AccountResource::options())->searchable()->required(),
                        TextInput::make('debit')->numeric()->minValue(0)->live(onBlur: true),
                        TextInput::make('credit')->numeric()->minValue(0)->live(onBlur: true),
                        TextInput::make('memo')->maxLength(255),
                    ])
                    ->defaultItems(2)->minItems(2)->reorderable(false)
                    ->addActionLabel(__('إضافة سطر')),
                Text::make(fn (Get $get) => self::totals($get('lines') ?? [])),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Parts::header(Heroicon::OutlinedBookOpen, fn (JournalEntry $r) => [
                'kicker' => __('قيد يومية').' · '.self::sourceLabel($r),
                'title' => $r->number,
                'sub' => [$r->entry_date->format('Y-m-d'), $r->description],
                'facts' => [
                    Parts::fact('المدين', Parts::amount($r->total)),
                    Parts::fact('الدائن', Parts::amount($r->total)),
                    Parts::fact('عدد الأطراف', $r->lines->count()),
                ],
            ]),
            Section::make(__('أطراف القيد'))->icon(Heroicon::OutlinedListBullet)->schema([
                ViewEntry::make('lines')->hiddenLabel()->view('filament.admin.journal-lines'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('lines.account'))
            ->defaultSort('entry_date', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('رقم القيد'))->searchable()->fontFamily('mono')->sortable(),
                TextColumn::make('entry_date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('description')->label(__('البيان'))->searchable()->wrap()->limit(90)
                    ->description(fn (JournalEntry $r) => $r->lines->sortByDesc('debit')->map(fn ($l) => $l->account?->displayName())->unique()->take(3)->implode(' · ')),
                TextColumn::make('source_type')->label(__('المصدر'))->badge()
                    ->state(fn (JournalEntry $r) => self::sourceLabel($r))
                    ->color(fn (JournalEntry $r) => $r->isManual() ? 'warning' : 'gray')
                    ->url(fn (JournalEntry $r) => self::sourceUrl($r)),
                TextColumn::make('total')->label(__('المبلغ'))->numeric(2)->sortable(),
            ])
            ->filters([
                Filter::make('period')->label(__('الفترة'))
                    ->schema([DatePicker::make('from')->label(__('من')), DatePicker::make('until')->label(__('إلى'))])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '<=', $d))),
                TernaryFilter::make('manual')->label(__('القيود اليدوية'))
                    ->queries(true: fn (Builder $query) => $query->whereNull('source_type'), false: fn (Builder $query) => $query->whereNotNull('source_type'), blank: fn (Builder $query) => $query),
                SelectFilter::make('account')->label(__('الحساب'))->options(fn () => AccountResource::options())->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $id) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $id)))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (JournalEntry $r) => $r->isManual()),
                DeleteAction::make()->visible(fn (JournalEntry $r) => $r->isManual()),
            ]);
    }

    /** Automatic entries follow their documents; only manual ones are edited or deleted by hand. */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof JournalEntry && $record->isManual() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof JournalEntry && $record->isManual() && parent::canDelete($record);
    }

    public static function sourceLabel(JournalEntry $entry): string
    {
        return __(self::SOURCES[$entry->source_type][0] ?? 'قيد يدوي');
    }

    public static function sourceUrl(JournalEntry $entry): ?string
    {
        $resource = self::SOURCES[$entry->source_type][1] ?? null;
        if ($resource === null || ! $resource::canViewAny()) {
            return null;
        }

        return $resource::hasPage('view') ? $resource::getUrl('view', ['record' => $entry->source_id]) : $resource::getUrl('index');
    }

    /** "Debit 1,000.00 · Credit 1,000.00 — balanced" under the lines while typing. */
    public static function totals(array $lines): string
    {
        $debit = round(collect($lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $credit = round(collect($lines)->sum(fn ($l) => (float) ($l['credit'] ?? 0)), 2);
        $state = abs($debit - $credit) < 0.005 ? __('متوازن') : __('الفرق :diff', ['diff' => number_format(abs($debit - $credit), 2)]);

        return __('إجمالي المدين :debit · إجمالي الدائن :credit — :state', ['debit' => number_format($debit, 2), 'credit' => number_format($credit, 2), 'state' => $state]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'description'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return __('قيد :number', ['number' => $record->number]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('البيان') => $record->description, __('المبلغ') => static::searchMoney($record->total)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'create' => CreateJournalEntry::route('/create'),
            'view' => ViewJournalEntry::route('/{record}'),
            'edit' => EditJournalEntry::route('/{record}/edit'),
        ];
    }
}
