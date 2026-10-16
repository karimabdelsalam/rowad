<?php

namespace App\Filament\Admin\Resources\EmployeeAdvances;

use App\Filament\Admin\Resources\EmployeeAdvances\Pages\ManageEmployeeAdvances;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Resource;
use App\Models\BankAccount;
use App\Models\EmployeeAdvance;
use App\Services\Finance\VoucherService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Salary advances (سلف) paid now and recovered from the next payrolls in instalments. */
class EmployeeAdvanceResource extends Resource
{
    protected static ?string $model = EmployeeAdvance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|\UnitEnum|null $navigationGroup = Hr::GROUP;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'سلفة';

    protected static ?string $pluralModelLabel = 'السلف';

    protected static ?string $permission = 'employee_advances';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Hr::employeeSelect()->columnSpanFull(),
                TextInput::make('amount')->label(__('المبلغ'))->required()->numeric()->minValue(1)
                    ->disabled(fn (?EmployeeAdvance $record) => $record !== null && (float) $record->remaining < (float) $record->amount),
                TextInput::make('installment_amount')->label(__('القسط الشهري'))->required()->numeric()->minValue(1)
                    ->helperText(__('يُخصم تلقائيًا من كل مسيّر رواتب حتى السداد.')),
                DatePicker::make('issued_at')->label(__('تاريخ الصرف'))->default(today())->required(),
                Textarea::make('notes')->label(__('ملاحظات'))->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'paymentVoucher']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')->label(__('الموظف'))->weight('bold')->searchable(),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->weight('bold'),
                TextColumn::make('installment_amount')->label(__('القسط'))->numeric(2),
                TextColumn::make('remaining')->label(__('المتبقي'))->numeric(2)->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('state')->label(__('الحالة'))->badge()
                    ->state(fn (EmployeeAdvance $r) => (float) $r->remaining <= 0 ? __('مسددة') : ($r->payment_voucher_id ? __('مصروفة') : __('لم تُصرف')))
                    ->color(fn (EmployeeAdvance $r) => (float) $r->remaining <= 0 ? 'success' : ($r->payment_voucher_id ? 'info' : 'warning')),
                TextColumn::make('issued_at')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('paymentVoucher.number')->label(__('سند الصرف'))->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('employee_id')->label(__('الموظف'))->relationship('employee', 'full_name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('disburse')->label(__('صرف السلفة'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (EmployeeAdvance $r) => $r->payment_voucher_id === null && Hr::can('payment_vouchers.create'))
                    ->schema([
                        Select::make('bank_account_id')->label(__('من الحساب'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))->placeholder(__('الصندوق / نقدًا')),
                        Select::make('payment_method')->label(__('طريقة الدفع'))->options(\App\Enums\PaymentMethod::class)->default('transfer')->required(),
                    ])
                    ->action(function (EmployeeAdvance $r, array $data) {
                        $voucher = app(VoucherService::class)->createPayment([
                            'party_type' => 'employee', 'party_id' => $r->employee_id, 'party_name' => $r->employee->full_name,
                            'amount' => (float) $r->amount, 'payment_method' => $data['payment_method'], 'bank_account_id' => $data['bank_account_id'] ?? null,
                            'issued_at' => now(), 'reason' => __('سلفة للموظف :name', ['name' => $r->employee->full_name]),
                        ]);
                        $r->forceFill(['payment_voucher_id' => $voucher->id])->save();
                        Notification::make()->success()->title(__('صدر سند الصرف :n', ['n' => $voucher->number]))->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->visible(fn (EmployeeAdvance $r) => $r->payment_voucher_id === null && (float) $r->remaining >= (float) $r->amount),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageEmployeeAdvances::route('/')];
    }
}
