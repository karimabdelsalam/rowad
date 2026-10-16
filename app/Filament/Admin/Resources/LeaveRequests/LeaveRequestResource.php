<?php

namespace App\Filament\Admin\Resources\LeaveRequests;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Admin\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Resource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Hr\LeaveBalance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/** Leave requests with the statutory balances; approved unpaid leave is deducted by payroll. */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = Hr::GROUP;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'إجازة';

    protected static ?string $pluralModelLabel = 'الإجازات';

    protected static ?string $permission = 'leave_requests';

    public static function getNavigationBadge(): ?string
    {
        $n = LeaveRequest::where('status', LeaveStatus::Pending)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Hr::employeeSelect()->live(),
                Select::make('type')->label(__('النوع'))->options(LeaveType::class)->default('annual')->required()->live(),
                DatePicker::make('start_date')->label(__('من'))->required()->live()->default(today()),
                DatePicker::make('end_date')->label(__('إلى'))->required()->live()->afterOrEqual('start_date')->default(today()),
                Text::make(fn (Get $get) => self::summary($get))->columnSpanFull(),
                Textarea::make('reason')->label(__('السبب / ملاحظات'))->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    private static function summary(Get $get): string
    {
        $days = filled($get('start_date')) && filled($get('end_date')) ? (int) Carbon::parse($get('start_date'))->diffInDays(Carbon::parse($get('end_date'))) + 1 : 0;
        $text = trans_choice('{0} حدد المدة|{1} يوم واحد|{2} يومان|[3,*] :count يومًا', max(0, $days));
        $employee = $get('employee_id') ? Employee::find($get('employee_id')) : null;
        $type = $get('type') instanceof LeaveType ? $get('type') : LeaveType::tryFrom((string) $get('type'));
        if ($employee && $type === LeaveType::Annual) {
            $text .= ' — '.__('رصيد الإجازة السنوية المتبقي: :n يومًا', ['n' => LeaveBalance::remaining($employee)]);
        } elseif ($employee && $type === LeaveType::Sick) {
            $text .= ' — '.__('المستخدم من المرضية هذه السنة: :n يومًا (30 كاملة، 60 بثلاثة أرباع، 30 بلا أجر)', ['n' => LeaveBalance::sickUsed($employee)]);
        } elseif ($type?->statutoryDays()) {
            $text .= ' — '.__('الحد النظامي: :n أيام مدفوعة', ['n' => $type->statutoryDays()]);
        }

        return $text;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'approver']))
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')->label(__('الموظف'))->weight('bold')->searchable()
                    ->description(fn (LeaveRequest $r) => $r->employee?->job_title),
                TextColumn::make('type')->label(__('النوع'))->badge(),
                TextColumn::make('start_date')->label(__('من'))->date('Y-m-d')->sortable(),
                TextColumn::make('end_date')->label(__('إلى'))->date('Y-m-d'),
                TextColumn::make('days')->label(__('الأيام'))->numeric()->alignCenter()->badge()->color('gray'),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->description(fn (LeaveRequest $r) => $r->approver ? $r->approver->name.' · '.$r->approved_at?->format('Y-m-d') : null),
                TextColumn::make('reason')->label(__('السبب'))->limit(40)->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(LeaveStatus::class)->default('pending'),
                SelectFilter::make('type')->label(__('النوع'))->options(LeaveType::class),
                SelectFilter::make('employee_id')->label(__('الموظف'))->relationship('employee', 'full_name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('approve')->label(__('اعتماد'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->requiresConfirmation()
                    ->visible(fn (LeaveRequest $r) => $r->status === LeaveStatus::Pending && Hr::can('leave_requests.approve'))
                    ->modalDescription(fn (LeaveRequest $r) => $r->type === LeaveType::Annual && LeaveBalance::remaining($r->employee) < $r->days
                        ? __('تنبيه: الطلب يتجاوز رصيد الموظف المتبقي (:n يومًا).', ['n' => LeaveBalance::remaining($r->employee)]) : null)
                    ->action(function (LeaveRequest $r) {
                        $r->forceFill(['status' => LeaveStatus::Approved, 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
                        Notification::make()->success()->title(__('تم اعتماد الإجازة'))->send();
                    }),
                Action::make('reject')->label(__('رفض'))->icon(Heroicon::OutlinedXCircle)->color('danger')->requiresConfirmation()
                    ->visible(fn (LeaveRequest $r) => $r->status === LeaveStatus::Pending && Hr::can('leave_requests.approve'))
                    ->action(fn (LeaveRequest $r) => $r->forceFill(['status' => LeaveStatus::Rejected, 'approved_by' => auth()->id(), 'approved_at' => now()])->save()),
                Action::make('cancel')->label(__('إلغاء'))->icon(Heroicon::OutlinedMinusCircle)->color('gray')->requiresConfirmation()
                    ->visible(fn (LeaveRequest $r) => $r->status === LeaveStatus::Approved && $r->end_date->isFuture() && Hr::can('leave_requests.approve'))
                    ->action(fn (LeaveRequest $r) => $r->forceFill(['status' => LeaveStatus::Cancelled])->save()),
                EditAction::make()->visible(fn (LeaveRequest $r) => $r->status === LeaveStatus::Pending),
                DeleteAction::make()->visible(fn (LeaveRequest $r) => $r->status !== LeaveStatus::Approved),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeaveRequests::route('/')];
    }
}
