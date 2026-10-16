<?php

namespace App\Filament\Admin\Resources\EndOfServiceSettlements;

use App\Enums\EndOfServiceReason;
use App\Filament\Admin\Resources\EndOfServiceSettlements\Pages\CreateEndOfServiceSettlement;
use App\Filament\Admin\Resources\EndOfServiceSettlements\Pages\ListEndOfServiceSettlements;
use App\Filament\Admin\Resources\EndOfServiceSettlements\Pages\ViewEndOfServiceSettlement;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Infolists\Parts;
use App\Filament\Resource;
use App\Models\Employee;
use App\Models\EndOfServiceSettlement;
use App\Services\Hr\EndOfService;
use App\Services\Hr\LeaveBalance;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/** End-of-service settlements: the statutory award, unused leave and other dues, paid with one voucher. */
class EndOfServiceSettlementResource extends Resource
{
    protected static ?string $model = EndOfServiceSettlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = Hr::GROUP;

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'تسوية نهاية خدمة';

    protected static ?string $pluralModelLabel = 'نهاية الخدمة';

    protected static ?string $permission = 'end_of_service';

    public static function form(Schema $schema): Schema
    {
        $fill = function (Get $get, Set $set) {
            if ($employee = Employee::find($get('employee_id'))) {
                $set('last_wage', $employee->totalWage());
                $set('leave_balance_days', max(0, LeaveBalance::remaining($employee)));
            }
        };

        return $schema->columns(1)->components([
            Section::make(__('الموظف وسبب الانتهاء'))->icon(Heroicon::OutlinedUser)->columns(['default' => 1, 'md' => 3])->schema([
                Hr::employeeSelect()->live()->afterStateUpdated($fill),
                DatePicker::make('termination_date')->label(__('تاريخ انتهاء الخدمة'))->default(today())->required()->live(),
                Select::make('reason')->label(__('سبب الانتهاء'))->options(EndOfServiceReason::class)->default('resignation')->required()->live(),
            ]),
            Section::make(__('عناصر التسوية'))->icon(Heroicon::OutlinedCalculator)->columns(['default' => 1, 'md' => 4])->schema([
                TextInput::make('last_wage')->label(__('الأجر الأخير الشامل'))->numeric()->minValue(0)->required()->live(onBlur: true)
                    ->helperText(__('الأساسي + البدلات، حسب المادة 84')),
                TextInput::make('leave_balance_days')->label(__('رصيد الإجازات (أيام)'))->numeric()->minValue(0)->default(0)->live(onBlur: true),
                TextInput::make('other_dues')->label(__('مستحقات أخرى'))->numeric()->minValue(0)->default(0)->live(onBlur: true)->helperText(__('راتب الشهر الأخير، بدل إشعار…')),
                TextInput::make('deductions')->label(__('خصومات'))->numeric()->minValue(0)->default(0)->live(onBlur: true)->helperText(__('سلف متبقية، عهد…')),
                Text::make(fn (Get $get) => self::preview($get))->columnSpanFull(),
                Textarea::make('notes')->label(__('ملاحظات'))->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function preview(Get $get): \Illuminate\Support\HtmlString|string
    {
        $employee = Employee::find($get('employee_id'));
        if ($employee === null || blank($get('termination_date'))) {
            return __('اختر الموظف وتاريخ الانتهاء لعرض الحساب.');
        }
        $c = EndOfService::calculate($employee, Carbon::parse($get('termination_date')), ($get('reason') instanceof EndOfServiceReason ? $get('reason') : EndOfServiceReason::from($get('reason') ?: 'resignation')),
            (float) ($get('last_wage') ?: $employee->totalWage()), (float) ($get('leave_balance_days') ?: 0));
        $net = $c['award'] + $c['leave_amount'] + (float) ($get('other_dues') ?: 0) - (float) ($get('deductions') ?: 0);
        $rows = [
            [__('مدة الخدمة'), __(':y سنة', ['y' => $c['years']])],
            [__('الخمس سنوات الأولى (نصف شهر لكل سنة)'), number_format($c['first_five'], 2)],
            [__('ما بعد الخمس (شهر لكل سنة)'), number_format($c['after_five'], 2)],
            [__('المكافأة الكاملة'), number_format($c['full_award'], 2)],
            [__('النسبة المستحقة حسب السبب'), round($c['share'] * 100).' %'],
            [__('المكافأة المستحقة'), '<b>'.number_format($c['award'], 2).'</b>'],
            [__('بدل الإجازات (:d يوم × :w)', ['d' => $c['leave_days'], 'w' => number_format($c['daily_wage'], 2)]), number_format($c['leave_amount'], 2)],
            [__('صافي التسوية'), '<b style="color:#0f766e">'.number_format($net, 2).'</b>'],
        ];
        $html = '<dl class="rowad-status">';
        foreach ($rows as [$k, $v]) {
            $html .= '<div><dt>'.e($k).'</dt><dd>'.$v.'</dd></div>';
        }

        return new \Illuminate\Support\HtmlString($html.'</dl>');
    }

    /** Freeze the calculation on the record (the law and wages may change later). */
    public static function compute(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $c = EndOfService::calculate($employee, Carbon::parse($data['termination_date']), ($data['reason'] instanceof EndOfServiceReason ? $data['reason'] : EndOfServiceReason::from($data['reason'])),
            (float) $data['last_wage'], (float) ($data['leave_balance_days'] ?? 0));
        $net = round($c['award'] + $c['leave_amount'] + (float) ($data['other_dues'] ?? 0) - (float) ($data['deductions'] ?? 0), 2);

        return $data + ['service_years' => $c['years'], 'award' => $c['award'], 'leave_amount' => $c['leave_amount'], 'net' => $net, 'calculation' => $c, 'created_by' => auth()->id()];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedDocumentCheck, fn (EndOfServiceSettlement $r) => [
                'kicker' => __('تسوية نهاية خدمة').' · '.($r->reason?->getLabel() ?? ''),
                'title' => $r->employee?->full_name ?? '-',
                'sub' => [$r->employee?->job_title, __('انتهاء الخدمة :d', ['d' => $r->termination_date?->format('Y-m-d')]), __(':y سنة خدمة', ['y' => $r->service_years])],
                'facts' => [
                    Parts::fact('المكافأة', Parts::amount($r->award)),
                    Parts::fact('بدل الإجازات', Parts::amount($r->leave_amount)),
                    Parts::fact('صافي التسوية', Parts::amount($r->net), 'success'),
                    Parts::fact('الحالة', $r->status === 'paid' ? __('مصروفة') : __('مسودة'), $r->status === 'paid' ? 'success' : 'warning'),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الحساب'))->icon(Heroicon::OutlinedCalculator)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::money('last_wage', 'الأجر الأخير'),
                    TextEntry::make('calculation.first_five')->label(__('الخمس سنوات الأولى'))->numeric(2),
                    TextEntry::make('calculation.after_five')->label(__('ما بعد الخمس'))->numeric(2),
                    TextEntry::make('calculation.full_award')->label(__('المكافأة الكاملة'))->numeric(2),
                    TextEntry::make('calculation.share')->label(__('النسبة المستحقة'))->formatStateUsing(fn ($state) => round((float) $state * 100).' %'),
                    Parts::money('award', 'المكافأة المستحقة')->weight('bold'),
                    TextEntry::make('leave_balance_days')->label(__('رصيد الإجازات'))->numeric(),
                    Parts::money('leave_amount', 'بدل الإجازات'),
                    Parts::money('other_dues', 'مستحقات أخرى'),
                    Parts::money('deductions', 'خصومات')->color('danger'),
                    Parts::money('net', 'صافي التسوية')->weight('bold')->color('success')->columnSpan(2),
                    TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-')->columnSpanFull(),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('الصرف'))->icon(Heroicon::OutlinedBanknotes)->schema([
                    TextEntry::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => $state === 'paid' ? __('مصروفة') : __('مسودة'))->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
                    Parts::link('paymentVoucher.number', 'سند الصرف', fn (EndOfServiceSettlement $r) => $r->paymentVoucher ? \App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource::getUrl('view', ['record' => $r->paymentVoucher]) : null),
                ]),
                Parts::record(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employee'))
            ->defaultSort('termination_date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')->label(__('الموظف'))->weight('bold')->searchable(),
                TextColumn::make('reason')->label(__('السبب'))->badge()->color('gray'),
                TextColumn::make('termination_date')->label(__('تاريخ الانتهاء'))->date('Y-m-d')->sortable(),
                TextColumn::make('service_years')->label(__('سنوات الخدمة'))->numeric(2)->alignCenter(),
                TextColumn::make('award')->label(__('المكافأة'))->numeric(2),
                TextColumn::make('net')->label(__('الصافي'))->numeric(2)->weight('bold')->color('success'),
                TextColumn::make('status')->label(__('الحالة'))->badge()->formatStateUsing(fn ($state) => $state === 'paid' ? __('مصروفة') : __('مسودة'))->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEndOfServiceSettlements::route('/'),
            'create' => CreateEndOfServiceSettlement::route('/create'),
            'view' => ViewEndOfServiceSettlement::route('/{record}'),
        ];
    }
}
