<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use App\Support\PlanLimits;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        $limit = fn (string $name, string $label, string $suffix) => TextInput::make($name)->label(__($label))
            ->integer()->minValue(0)->suffix(__($suffix))->placeholder(__('غير محدود'))->helperText(__('اتركه فارغًا لعدم التحديد.'));

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('الباقة'))->columns(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('name_ar')->label(__('الاسم بالعربية'))->required()->maxLength(100),
                    TextInput::make('name_en')->label(__('الاسم بالإنجليزية'))->required()->maxLength(100),
                    TextInput::make('slug')->label(__('الرابط المختصر'))->required()->alphaDash()->maxLength(50)->unique(ignoreRecord: true)
                        ->extraInputAttributes(['dir' => 'ltr'])->helperText(__('معرّف داخلي بالإنجليزية، لا يتغير بعد الإنشاء.'))->disabledOn('edit'),
                    Textarea::make('description')->label(__('الوصف'))->rows(2)->columnSpanFull(),
                ]),
                Section::make(__('السعر والتجربة'))->columns(['default' => 1, 'md' => 4])->schema([
                    TextInput::make('monthly_price')->label(__('السعر الشهري'))->required()->numeric()->minValue(0)->default(0)->suffix(__('ر.س')),
                    TextInput::make('yearly_price')->label(__('السعر السنوي'))->required()->numeric()->minValue(0)->default(0)->suffix(__('ر.س')),
                    TextInput::make('trial_days')->label(__('أيام التجربة'))->required()->integer()->minValue(0)->default(14)->suffix(__('يوم')),
                    TextInput::make('currency')->label(__('العملة'))->required()->default('SAR')->length(3)->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Section::make(__('الحدود'))->description(__('ما تستطيع المؤسسة استخدامه في هذه الباقة.'))->columns(['default' => 1, 'md' => 4])->schema([
                    $limit('max_units', 'الوحدات', 'وحدة'),
                    $limit('max_users', 'المستخدمون', 'مستخدم'),
                    $limit('max_storage_mb', 'التخزين', 'ميجا'),
                    $limit('max_sms_per_month', 'رسائل SMS شهريًا', 'رسالة'),
                ]),
                Section::make(__('المزايا'))->schema([
                    CheckboxList::make('features')->hiddenLabel()
                        ->options(fn () => array_map('__', PlanLimits::FEATURES))
                        ->columns(['default' => 1, 'md' => 3])->bulkToggleable(),
                ]),
                Section::make(__('العرض'))->columns(['default' => 1, 'md' => 2])->schema([
                    Toggle::make('is_active')->label(__('متاحة للاشتراك'))->default(true)
                        ->helperText(__('الباقة غير المتاحة تختفي من صفحة الأسعار والتسجيل، وتبقى المؤسسات المشتركة عليها كما هي.')),
                    TextInput::make('sort_order')->label(__('الترتيب'))->required()->integer()->default(0),
                ]),
            ]);
    }
}
