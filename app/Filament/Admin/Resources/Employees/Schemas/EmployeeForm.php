<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Enums\Calendar;
use App\Filament\Admin\Resources\Concerns\PersonFormSections;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PersonFormSections::name(),
                PersonFormSections::identity([
                    DatePicker::make('id_expiry_date')->helperText(__('يصل تنبيه قبل انتهاء الهوية بـ 15 يومًا')),
                ]),
                PersonFormSections::contact([
                    TextInput::make('mobile2')->tel()->maxLength(30),
                    Textarea::make('address2')->rows(2)->columnSpanFull(),
                ]),
                Section::make(__('الوظيفة'))->icon(Heroicon::OutlinedBriefcase)->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    TextInput::make('job_title')->label(__('المسمى الوظيفي'))->maxLength(190),
                    TextInput::make('department')->label(__('القسم'))->maxLength(190),
                    Select::make('employment_type')->label(__('نوع التوظيف'))->options(['full_time' => __('دوام كامل'), 'part_time' => __('دوام جزئي'), 'contract' => __('عقد مؤقت')])->default('full_time')->required(),
                    Select::make('status')->label(__('الحالة'))->options(['active' => __('على رأس العمل'), 'on_leave' => __('في إجازة'), 'terminated' => __('منتهي الخدمة')])->default('active')->required(),
                    DatePicker::make('start_date')->label(__('تاريخ التعيين')),
                    DatePicker::make('contract_end_date')->label(__('نهاية العقد')),
                    TextInput::make('weekly_hours')->label(__('ساعات العمل الأسبوعية'))->integer()->default(48)->minValue(1)->maxValue(60),
                    Select::make('calendar')->options(Calendar::class)->default('gregorian')->required(),
                    TextInput::make('education')->maxLength(190),
                    TextInput::make('marital_status')->maxLength(50),
                ]),
                Section::make(__('الراتب والتأمينات'))->icon(Heroicon::OutlinedBanknotes)->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->description(__('الأجر الخاضع للتأمينات = الأساسي + بدل السكن. مكافأة نهاية الخدمة والإجازات تُحسب على الأجر الكامل.'))
                    ->schema([
                        TextInput::make('salary')->label(__('الراتب الأساسي'))->required()->numeric()->minValue(0)->default(0),
                        TextInput::make('housing_allowance')->label(__('بدل السكن'))->numeric()->minValue(0)->default(0),
                        TextInput::make('transport_allowance')->label(__('بدل النقل'))->numeric()->minValue(0)->default(0),
                        TextInput::make('other_allowances')->label(__('بدلات أخرى'))->numeric()->minValue(0)->default(0),
                        Toggle::make('gosi_subscribed')->label(__('مسجل في التأمينات الاجتماعية'))->default(true)->inline(false),
                        TextInput::make('gosi_number')->label(__('رقم الاشتراك في التأمينات'))->maxLength(30)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('annual_leave_days')->label(__('رصيد الإجازة السنوية'))->integer()->minValue(0)->maxValue(60)->placeholder(__('حسب النظام (21 / 30)')),
                        TextInput::make('rent_commission')->label(__('عمولة الإيجار %'))->required()->numeric()->minValue(0)->default(0),
                        TextInput::make('sale_commission')->label(__('عمولة البيع %'))->required()->numeric()->minValue(0)->default(0),
                        Select::make('bank_id')->label(__('البنك'))->relationship('bank', 'title')->searchable()->preload(),
                        TextInput::make('iban')->label(__('الآيبان'))->maxLength(34)->extraInputAttributes(['dir' => 'ltr'])->placeholder('SA00 0000 0000 0000 0000 0000')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? strtoupper(str_replace(' ', '', $state)) : null)->columnSpan(2),
                    ]),
                PersonFormSections::notesAndFiles(notes: false),
                PersonFormSections::account(),
            ]);
    }
}
