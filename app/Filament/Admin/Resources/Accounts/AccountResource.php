<?php

namespace App\Filament\Admin\Resources\Accounts;

use App\Enums\AccountType;
use App\Filament\Admin\Pages\Accounting\AccountLedger;
use App\Filament\Admin\Resources\Accounts\Pages\ManageAccounts;
use App\Filament\Resource;
use App\Models\Account;
use App\Services\Accounting\Statements;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** دليل الحسابات: the office's chart of accounts, with each account's balance. */
class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'المحاسبة';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'حساب';

    protected static ?string $pluralModelLabel = 'دليل الحسابات';

    protected static ?string $permission = 'accounts';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('parent_id')->label(__('الحساب الرئيسي'))
                ->options(fn (?Account $record) => Account::query()->where('is_group', true)->when($record, fn ($q) => $q->whereKeyNot($record->id))
                    ->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->label()]))
                ->searchable()->live()
                ->afterStateUpdated(function (?string $state, Set $set) {
                    if ($parent = Account::find($state)) {
                        $set('type', $parent->type->value);
                        $set('code', self::nextCode($parent));
                    }
                })
                ->disabled(fn (?Account $record) => $record?->system_key !== null)
                ->required(fn (?Account $record) => $record === null),
            Select::make('type')->label(__('النوع'))->options(AccountType::class)->required()
                ->disabled(fn (Get $get) => filled($get('parent_id')))->dehydrated(),
            TextInput::make('code')->label(__('رقم الحساب'))->required()->maxLength(20)->regex('/^\d+$/')
                ->unique(ignoreRecord: true)
                ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    $parent = Account::find($get('parent_id'));
                    if ($parent && (! str_starts_with((string) $value, $parent->code) || $value === $parent->code)) {
                        $fail(__('رقم الحساب يبدأ برقم الحساب الرئيسي :code', ['code' => $parent->code]));
                    }
                })
                ->helperText(__('يبدأ برقم الحساب الرئيسي، مثل 5301 تحت 53')),
            TextInput::make('name')->label(__('اسم الحساب'))->required()->maxLength(255),
            TextInput::make('name_en')->label(__('الاسم بالإنجليزية'))->maxLength(255),
            Toggle::make('is_group')->label(__('حساب رئيسي (تجميعي)'))->helperText(__('يضم حسابات فرعية ولا تُسجّل عليه قيود مباشرة'))
                ->disabled(fn (?Account $record) => $record !== null && $record->lines()->exists()),
            Toggle::make('is_active')->label(__('نشط'))->default(true),
            Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('code')->label(__('الرقم'))->searchable()->sortable()->fontFamily('mono')
                    ->weight(fn (Account $r) => $r->is_group ? 'bold' : null),
                TextColumn::make('name')->label(__('الحساب'))->searchable(['name', 'name_en'])
                    ->state(fn (Account $r) => $r->displayName())
                    ->weight(fn (Account $r) => $r->is_group ? 'bold' : null)
                    ->extraAttributes(fn (Account $r) => ['style' => 'padding-inline-start:'.(strlen($r->code) - 1) * 14 .'px'])
                    ->description(fn (Account $r) => $r->bank_account_id ? __('حساب بنكي') : null)
                    ->tooltip(fn (Account $r) => $r->system_key ? __('يستخدمه الترحيل التلقائي') : null),
                TextColumn::make('type')->label(__('النوع'))->badge(),
                TextColumn::make('balance')->label(__('الرصيد'))
                    ->state(fn (Account $r) => self::balances()[$r->id] ?? 0)
                    ->numeric(2)
                    ->color(fn ($state) => $state < 0 ? 'danger' : null)
                    ->weight(fn (Account $r) => $r->is_group ? 'bold' : null),
                IconColumn::make('is_active')->label(__('نشط'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('النوع'))->options(AccountType::class),
                TernaryFilter::make('is_group')->label(__('الحسابات الرئيسية')),
            ])
            ->recordActions([
                Action::make('ledger')->label(__('كشف الحساب'))->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->visible(fn () => AccountLedger::canAccess())
                    ->url(fn (Account $r) => AccountLedger::getUrl(['account' => $r->id])),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Account $r) => $r->isDeletable()),
            ]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Account ? $record->label() : '';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'name_en'];
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccounts::route('/')];
    }

    /** Accounts a voucher or entry may be posted to. @param list<AccountType> $types @return array<int, string> */
    public static function options(array $types = []): array
    {
        return Account::query()->postable()->when($types, fn ($q) => $q->whereIn('type', $types))
            ->orderBy('code')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all();
    }

    public static function nextCode(Account $parent): string
    {
        $last = Account::query()->where('parent_id', $parent->id)->pluck('code')->map(fn ($c) => (int) $c)->max();

        return (string) ($last ? $last + 1 : $parent->code.'01');
    }

    /** Closing balance per account (headings include their sub-accounts), once per request. @return array<int, float> */
    private static function balances(): array
    {
        return once(fn () => app(Statements::class)->trialBalance(null, null, withEmpty: true)
            ->mapWithKeys(fn (array $r) => [$r['account']->id => $r['closing']])->all());
    }
}
