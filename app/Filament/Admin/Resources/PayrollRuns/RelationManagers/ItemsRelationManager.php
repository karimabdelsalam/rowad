<?php

namespace App\Filament\Admin\Resources\PayrollRuns\RelationManagers;

use App\Enums\PayrollStatus;
use App\Filament\Admin\Resources\Hr;
use App\Models\PayrollItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The payroll lines: one per employee, with the adjustable parts editable while the run is a draft. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('سطور المسيّر');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('overtime')->label(__('ساعات إضافية (مبلغ)'))->numeric()->minValue(0)->default(0),
            TextInput::make('bonus')->label(__('مكافأة'))->numeric()->minValue(0)->default(0),
            TextInput::make('penalties')->label(__('حسومات'))->numeric()->minValue(0)->default(0),
            TextInput::make('advances')->label(__('قسط سلفة'))->numeric()->minValue(0)->default(0),
            TextInput::make('other_deductions')->label(__('خصومات أخرى'))->numeric()->minValue(0)->default(0),
            TextInput::make('gosi_employee')->label(__('تأمينات (حصة الموظف)'))->numeric()->minValue(0)->default(0),
            Textarea::make('notes')->label(__('ملاحظة تظهر في القسيمة'))->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $money = fn (string $name, string $label) => TextColumn::make($name)->label(__($label))->numeric(2)->summarize(Sum::make()->numeric(2)->label(''));

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employee'))
            ->defaultSort('employee_id')
            ->striped()
            ->paginated(false)
            ->columns([
                TextColumn::make('employee.full_name')->label(__('الموظف'))->weight('bold')->searchable()
                    ->description(fn (PayrollItem $r) => $r->employee?->job_title ?: $r->employee?->iban),
                $money('basic', 'الأساسي'),
                TextColumn::make('allowances')->label(__('البدلات'))->numeric(2)
                    ->state(fn (PayrollItem $r) => (float) $r->housing + (float) $r->transport + (float) $r->other_allowances),
                $money('overtime', 'إضافي'),
                $money('bonus', 'مكافأة'),
                $money('gross', 'الإجمالي')->weight('bold'),
                $money('penalties', 'حسومات')->color('danger'),
                $money('advances', 'سلف')->color('danger'),
                TextColumn::make('unpaid_leave')->label(__('إجازة بلا راتب'))->numeric(2)->color('danger')
                    ->description(fn (PayrollItem $r) => $r->unpaid_leave_days ? trans_choice('{1} يوم واحد|[2,*] :count أيام', $r->unpaid_leave_days) : null)->toggleable(),
                $money('gosi_employee', 'تأمينات')->color('danger'),
                $money('other_deductions', 'أخرى')->color('danger')->toggleable(),
                $money('net', 'الصافي')->weight('bold')->color('success'),
                TextColumn::make('paymentVoucher.number')->label(__('السند'))->placeholder('-')->toggleable(),
            ])
            ->recordActions([
                EditAction::make()->label(__('تعديل'))
                    ->visible(fn () => $this->getOwnerRecord()->status === PayrollStatus::Draft && Hr::can('payroll.update'))
                    ->after(fn () => $this->getOwnerRecord()->refreshTotals()),
                Action::make('payslip')->label(__('قسيمة'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (PayrollItem $r) => route('documents.print', ['type' => 'payslip', 'id' => $r->getKey()]), shouldOpenInNewTab: true),
                \App\Filament\Support\DocumentActions::pdf('payslip')->iconButton(),
            ]);
    }
}
