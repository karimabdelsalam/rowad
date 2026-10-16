<?php

namespace App\Filament\Admin\Resources\EmployeePenalties;

use App\Enums\Calendar;
use App\Filament\Admin\Resources\EmployeePenalties\Pages\ManageEmployeePenalties;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Resource;
use App\Models\EmployeePenalty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Legacy "الحسومات": deductions from a month's salary; payroll picks them up by month. */
class EmployeePenaltyResource extends Resource
{
    protected static ?string $model = EmployeePenalty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = Hr::GROUP;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'حسم / جزاء';

    protected static ?string $pluralModelLabel = 'الحسومات والجزاءات';

    protected static ?string $permission = 'employee_penalties';

    public const REASONS = ['absence' => 'غياب', 'late' => 'تأخير', 'violation' => 'مخالفة', 'damage' => 'إتلاف', 'other' => 'أخرى'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Hr::employeeSelect()->columnSpanFull(),
                TextInput::make('amount')->label(__('المبلغ'))->required()->numeric()->minValue(0.01),
                Select::make('reason')->label(__('السبب'))->options(fn () => array_map('__', self::REASONS))->default('other')->required(),
                Select::make('for_month')->label(__('يُخصم من راتب شهر'))->options(Hr::months())->default((int) now()->month)->required(),
                TextInput::make('for_year')->label(__('السنة'))->integer()->default((int) now()->year)->required(),
                DatePicker::make('issued_at')->label(__('تاريخ القرار'))->default(today())->required(),
                Select::make('calendar')->label(__('التقويم'))->options(Calendar::class)->default('gregorian')->required(),
                Textarea::make('reason_text')->label(__('التفاصيل'))->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employee'))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')->label(__('الموظف'))->weight('bold')->searchable()
                    ->description(fn (EmployeePenalty $r) => $r->employee?->job_title),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->color('danger')->weight('bold')->sortable(),
                TextColumn::make('reason')->label(__('السبب'))->badge()->color('gray')->formatStateUsing(fn ($state) => __(self::REASONS[$state] ?? $state ?? 'أخرى')),
                TextColumn::make('period')->label(__('من راتب شهر'))->state(fn (EmployeePenalty $r) => Hr::months()[$r->for_month].' '.$r->for_year),
                TextColumn::make('issued_at')->label(__('تاريخ القرار'))->date('Y-m-d')->sortable(),
                TextColumn::make('reason_text')->label(__('التفاصيل'))->limit(40)->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('employee_id')->label(__('الموظف'))->relationship('employee', 'full_name')->searchable()->preload(),
                SelectFilter::make('for_month')->label(__('الشهر'))->options(Hr::months()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageEmployeePenalties::route('/')];
    }
}
