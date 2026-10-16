<?php

namespace App\Filament\Admin\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Legacy home/settings: every office-level setting except the website. */
class GeneralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 0;

    /** setting key => group */
    public const KEYS = [
        'site_name' => 'identity', 'site_name_en' => 'identity', 'logo' => 'identity', 'brand_color' => 'identity', 'address1' => 'identity', 'address2' => 'identity',
        'phone' => 'identity', 'mobile' => 'identity', 'fax' => 'identity', 'email' => 'identity', 'vat_number' => 'identity',
        'commercial_register' => 'identity', 'zatca_street' => 'identity', 'zatca_building_number' => 'identity',
        'zatca_district' => 'identity', 'zatca_postal_code' => 'identity',
        'currency' => 'localization', 'phone_code' => 'localization', 'timezone' => 'localization', 'system_calendar' => 'localization',
        'salary_calendar' => 'localization', 'locale' => 'localization',
        'vat_percent' => 'taxes', 'residential_tax_enabled' => 'taxes', 'residential_tax_percent' => 'taxes',
        'commercial_tax_enabled' => 'taxes', 'commercial_tax_percent' => 'taxes', 'income_tax_enabled' => 'taxes',
        'income_tax_percent' => 'taxes', 'owner_tax_enabled' => 'taxes', 'owner_tax_percent' => 'taxes',
        'mail_driver' => 'mail', 'smtp_host' => 'mail', 'smtp_port' => 'mail', 'smtp_username' => 'mail', 'smtp_password' => 'mail',
        'sms_provider' => 'sms', 'sms_username' => 'sms', 'sms_password' => 'sms', 'sms_sender' => 'sms',
        'sms_login_staff' => 'sms', 'sms_login_clients' => 'sms',
        'support_ticket_prefix' => 'general', 'google_maps_key' => 'general', 'maintenance_approval_limit' => 'general',
        'payments_gateway' => 'payments', 'moyasar_publishable_key' => 'payments', 'moyasar_secret_key' => 'payments',
        'moyasar_webhook_secret' => 'payments', 'payments_bank_account_id' => 'payments', 'payments_methods' => 'payments',
        'whatsapp_enabled' => 'whatsapp', 'whatsapp_phone_number_id' => 'whatsapp', 'whatsapp_access_token' => 'whatsapp',
        'whatsapp_template' => 'whatsapp', 'whatsapp_language' => 'whatsapp',
        'hr_gosi_saudi_employee' => 'hr', 'hr_gosi_saudi_employer' => 'hr', 'hr_gosi_expat_employer' => 'hr', 'hr_gosi_wage_cap' => 'hr',
        'hr_annual_leave_days' => 'hr', 'hr_annual_leave_days_senior' => 'hr', 'hr_overtime_rate' => 'hr', 'hr_payroll_day' => 'hr',
        'hr_wps_establishment_id' => 'hr',
    ];

    /** Legacy class.sms.php providers. */
    public const SMS_PROVIDERS = [
        'unifonic' => 'Unifonic', 'mobilyws' => 'Mobily.ws', 'mobilysms' => 'Mobily SMS', 'mrasel' => 'Mrasel', 'hdvbx' => 'HDVBX',
        'twilio' => 'Twilio', 'nexmo' => 'Vonage (Nexmo)', 'clickatell' => 'Clickatell', 'clicksend' => 'ClickSend',
        'bulksms' => 'BulkSMS', 'smsglobal' => 'SMSGlobal', 'smsapi' => 'SMSAPI', 'msg91' => 'MSG91',
    ];

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('الإعدادات العامة');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('الإعدادات العامة');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    public function mount(): void
    {
        $defaults = ['whatsapp_language' => 'ar'];
        $this->form->fill(collect(self::KEYS)->keys()->mapWithKeys(fn ($key) => [$key => $key === 'payments_methods'
            ? Settings::list($key, array_keys(\App\Services\Payments\Payments::METHODS)) : Settings::get($key, $defaults[$key] ?? null)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $tax = fn (string $prefix, string $label) => Grid::make(2)->schema([
            Toggle::make("{$prefix}_enabled")->label($label)->live(),
            TextInput::make("{$prefix}_percent")->label(__('النسبة %'))->numeric()->minValue(0)->maxValue(100)
                ->visible(fn (Get $get) => (bool) $get("{$prefix}_enabled")),
        ]);

        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make(__('بيانات المنشأة'))->columns(2)->schema([
                    TextInput::make('site_name')->label(__('اسم المؤسسة'))->required(),
                    TextInput::make('site_name_en')->label(__('اسم المؤسسة بالإنجليزية')),
                    FileUpload::make('logo')->label(__('الشعار'))->image()->disk('public')->directory('branding')->imagePreviewHeight('96')
                        ->helperText(__('يظهر في لوحة الإدارة وبوابات الملاك والمستأجرين وصفحة الدخول والمطبوعات وموقع المؤسسة.')),
                    ColorPicker::make('brand_color')->label(__('لون الهوية'))->hex()
                        ->helperText(__('اتركه فارغًا للون الافتراضي. يُلوّن الأزرار والقائمة الجانبية بلون مؤسستك.')),
                    TextInput::make('address1')->label(__('المدينة')),
                    TextInput::make('address2')->label(__('العنوان')),
                    TextInput::make('phone')->label(__('هاتف'))->tel(),
                    TextInput::make('mobile')->label(__('الجوال'))->tel(),
                    TextInput::make('fax')->label(__('الفاكس')),
                    TextInput::make('email')->label(__('البريد الإلكتروني'))->email(),
                    TextInput::make('vat_number')->label(__('الرقم الضريبي'))->regex('/^3\d{13}3$/')->helperText(__('15 رقمًا يبدأ وينتهي بالرقم 3 (للفوترة الإلكترونية)')),
                    TextInput::make('commercial_register')->label(__('رقم السجل التجاري')),
                    \Filament\Schemas\Components\Fieldset::make(__('العنوان الوطني (للفوترة الإلكترونية)'))->columns(['default' => 1, 'md' => 4])->columnSpanFull()->schema([
                        TextInput::make('zatca_street')->label(__('اسم الشارع'))->maxLength(127),
                        TextInput::make('zatca_building_number')->label(__('رقم المبنى'))->regex('/^\d{4}$/')->maxLength(4)->helperText(__('4 أرقام')),
                        TextInput::make('zatca_district')->label(__('الحي'))->maxLength(127),
                        TextInput::make('zatca_postal_code')->label(__('الرمز البريدي'))->regex('/^\d{5}$/')->maxLength(5)->helperText(__('5 أرقام')),
                    ]),
                ]),
                Tab::make(__('اللغة والتقويم'))->columns(2)->schema([
                    Select::make('locale')->label(__('لغة النظام الافتراضية'))->options(['ar' => 'العربية', 'en' => 'English'])->required(),
                    Select::make('system_calendar')->label(__('تقويم النظام'))->options(\App\Enums\Calendar::class)->required(),
                    Select::make('salary_calendar')->label(__('تقويم الرواتب'))->options(\App\Enums\Calendar::class)->required(),
                    Select::make('timezone')->label(__('المنطقة الزمنية'))->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable()->required(),
                    TextInput::make('currency')->label(__('العملة'))->maxLength(5)->required(),
                    TextInput::make('phone_code')->label(__('مفتاح الدولة'))->numeric(),
                ]),
                Tab::make(__('الضرائب'))->schema([
                    TextInput::make('vat_percent')->label(__('نسبة ضريبة القيمة المضافة %'))->numeric()->minValue(0)->maxValue(100)->required(),
                    $tax('residential_tax', __('ضريبة السكني')),
                    $tax('commercial_tax', __('ضريبة التجاري')),
                    $tax('income_tax', __('ضريبة دخل المؤسسة (على العمولات ورسوم الإدارة)')),
                    $tax('owner_tax', __('ضريبة على المالك')),
                ]),
                Tab::make(__('البريد الإلكتروني'))->columns(2)->schema([
                    Select::make('mail_driver')->label(__('طريقة الإرسال'))->live()
                        ->options(['platform' => __('خادم المنصة'), 'smtp' => 'SMTP'])->required(),
                    TextInput::make('smtp_host')->label('SMTP Host')->visible(fn (Get $get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_port')->label('SMTP Port')->numeric()->visible(fn (Get $get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_username')->label(__('اسم المستخدم'))->visible(fn (Get $get) => $get('mail_driver') === 'smtp'),
                    TextInput::make('smtp_password')->label(__('كلمة المرور'))->password()->revealable()->visible(fn (Get $get) => $get('mail_driver') === 'smtp'),
                ]),
                Tab::make(__('الرسائل النصية'))->columns(2)->schema([
                    Select::make('sms_provider')->label(__('مزود الخدمة'))->options(self::SMS_PROVIDERS)->searchable(),
                    TextInput::make('sms_sender')->label(__('اسم المرسل')),
                    TextInput::make('sms_username')->label(__('اسم المستخدم / API Key')),
                    TextInput::make('sms_password')->label(__('كلمة المرور / Secret'))->password()->revealable(),
                    Toggle::make('sms_login_staff')->label(__('دخول الموظفين برمز SMS')),
                    Toggle::make('sms_login_clients')->label(__('دخول الملاك والعملاء برمز SMS')),
                    Actions::make([
                        Action::make('smsBalance')
                            ->label(__('فحص الرصيد والاتصال'))
                            ->icon(\Filament\Support\Icons\Heroicon::OutlinedSignal)
                            ->color('gray')
                            ->action(function (Get $get) {
                                $result = app(\App\Services\Messaging\SmsGateway::class)->balance([
                                    'provider' => $get('sms_provider'), 'api_key' => $get('sms_username'),
                                    'secret_key' => filled($get('sms_password')) ? $get('sms_password') : \App\Support\Settings::get('sms_password'),
                                    'sender' => $get('sms_sender'),
                                ]);
                                $notification = match (true) {
                                    $result === false => \Filament\Notifications\Notification::make()->title(__('فشل في الربط مع مزود الخدمة'))->danger(),
                                    $result === null => \Filament\Notifications\Notification::make()->title(__('هذا المزود لا يتيح فحص الرصيد'))->warning(),
                                    default => \Filament\Notifications\Notification::make()->title(__('تم الاتصال بنجاح'))->body(__('الرصيد: :balance', ['balance' => $result]))->success(),
                                };
                                $notification->send();
                            }),
                    ])->columnSpanFull(),
                ]),
                Tab::make(__('واتساب'))->columns(2)->schema([
                    Toggle::make('whatsapp_enabled')->label(__('إرسال التنبيهات عبر واتساب للأعمال'))->live()->columnSpanFull()
                        ->helperText(__('تذهب تذكيرات الأقساط والعقود والسندات عبر واتساب أولًا، وإن تعذّر الإرسال تُرسل رسالة نصية تلقائيًا. رموز الدخول تبقى رسائل نصية.')),
                    TextInput::make('whatsapp_phone_number_id')->label(__('معرّف رقم الهاتف (Phone number ID)'))->visible(fn (Get $get) => (bool) $get('whatsapp_enabled'))->regex('/^\d{5,25}$/'),
                    TextInput::make('whatsapp_access_token')->label(__('رمز الوصول الدائم (Access token)'))->password()->revealable()->visible(fn (Get $get) => (bool) $get('whatsapp_enabled')),
                    TextInput::make('whatsapp_template')->label(__('اسم القالب المعتمد'))->placeholder('aqarnova_notice')->visible(fn (Get $get) => (bool) $get('whatsapp_enabled'))
                        ->regex('/^[a-z0-9_]{1,512}$/')
                        ->helperText(__('أنشئ في WhatsApp Manager قالبًا من فئة Utility نصه متغير واحد، مثل: "{{1}}"، واكتب اسمه هنا. يُرسل نص الرسالة من قوالب الإشعارات في هذا المتغير.')),
                    Select::make('whatsapp_language')->label(__('لغة القالب'))->options(['ar' => 'العربية (ar)', 'en' => 'English (en)', 'en_US' => 'English US (en_US)'])->default('ar')
                        ->visible(fn (Get $get) => (bool) $get('whatsapp_enabled')),
                ]),
                Tab::make(__('الدفع الإلكتروني'))->columns(2)->schema([
                    Select::make('payments_gateway')->label(__('بوابة الدفع'))->options(['' => __('بدون — تحصيل يدوي'), 'moyasar' => 'Moyasar (مدى، فيزا/ماستركارد، Apple Pay، STC Pay)'])->live()
                        ->helperText(fn () => \App\Support\PlanLimits::allows('online_payments') ? __('يُرسل رابط دفع مع كل تذكير ويظهر زر "ادفع الآن" في بوابة المستأجرين؛ عند الدفع يُحصَّل القسط ويصدر سند القبض تلقائيًا.') : __('التحصيل الإلكتروني غير مشمول في باقتك الحالية.'))
                        ->columnSpanFull(),
                    TextInput::make('moyasar_publishable_key')->label(__('المفتاح العام (Publishable)'))->placeholder('pk_live_…')->visible(fn (Get $get) => $get('payments_gateway') === 'moyasar'),
                    TextInput::make('moyasar_secret_key')->label(__('المفتاح السري (Secret)'))->password()->revealable()->placeholder('sk_live_…')->visible(fn (Get $get) => $get('payments_gateway') === 'moyasar'),
                    TextInput::make('moyasar_webhook_secret')->label(__('رمز Webhook السري'))->password()->revealable()->visible(fn (Get $get) => $get('payments_gateway') === 'moyasar')
                        ->helperText(fn () => __('في لوحة Moyasar: Webhooks → أضف الرابط :url بهذا الرمز، ليصل تأكيد الدفع حتى لو أغلق العميل المتصفح.', ['url' => url('/webhooks/moyasar')])),
                    Select::make('payments_bank_account_id')->label(__('الحساب البنكي للإيداع'))->options(fn () => \App\Models\BankAccount::query()->with('bank')->orderBy('id')->get()->mapWithKeys(fn ($a) => [$a->id => trim(($a->bank?->name ?? '').' — '.($a->iban ?: $a->account_number), ' —')])->all())->placeholder(__('بدون'))
                        ->visible(fn (Get $get) => $get('payments_gateway') === 'moyasar')->helperText(__('يُسجَّل كل سداد إلكتروني كإيداع في هذا الحساب.')),
                    \Filament\Forms\Components\CheckboxList::make('payments_methods')->label(__('طرق الدفع المتاحة'))->options(collect(\App\Services\Payments\Payments::METHODS)->map(fn ($l) => __($l))->all())
                        ->default(array_keys(\App\Services\Payments\Payments::METHODS))->columns(3)->visible(fn (Get $get) => $get('payments_gateway') === 'moyasar')->columnSpanFull(),
                ]),
                Tab::make(__('الموارد البشرية'))->columns(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('hr_gosi_saudi_employee')->label(__('التأمينات — حصة الموظف السعودي %'))->numeric()->placeholder('9.75'),
                    TextInput::make('hr_gosi_saudi_employer')->label(__('التأمينات — حصة المنشأة عن السعودي %'))->numeric()->placeholder('11.75'),
                    TextInput::make('hr_gosi_expat_employer')->label(__('التأمينات — حصة المنشأة عن غير السعودي %'))->numeric()->placeholder('2'),
                    TextInput::make('hr_gosi_wage_cap')->label(__('الحد الأعلى للأجر الخاضع'))->numeric()->placeholder('45000'),
                    TextInput::make('hr_annual_leave_days')->label(__('الإجازة السنوية (أيام)'))->integer()->placeholder('21'),
                    TextInput::make('hr_annual_leave_days_senior')->label(__('الإجازة السنوية بعد 5 سنوات'))->integer()->placeholder('30'),
                    TextInput::make('hr_overtime_rate')->label(__('معامل الساعة الإضافية'))->numeric()->placeholder('1.5'),
                    TextInput::make('hr_payroll_day')->label(__('يوم صرف الرواتب'))->integer()->minValue(1)->maxValue(31)->placeholder('27'),
                    TextInput::make('hr_wps_establishment_id')->label(__('رقم المنشأة في وزارة الموارد البشرية (لملف حماية الأجور)'))->maxLength(30),
                ]),
                Tab::make(__('أخرى'))->columns(2)->schema([
                    TextInput::make('support_ticket_prefix')->label(__('بادئة رقم تذكرة الدعم')),
                    TextInput::make('google_maps_key')->label(__('مفتاح خرائط Google')),
                    TextInput::make('maintenance_approval_limit')->label(__('حد موافقة المالك على الصيانة'))->numeric()->minValue(0)
                        ->helperText(__('أعمال الصيانة التي تبلغ تكلفتها التقديرية هذا المبلغ أو أكثر تنتظر موافقة المالك من بوابته. اتركه فارغًا لعدم طلب الموافقة.')),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')->footer([
                Actions::make([Action::make('save')->label(__('حفظ'))->submit('save')]),
            ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (array_key_exists($key, self::KEYS)) {
                Settings::set($key, $value instanceof BackedEnum ? $value->value : $value, self::KEYS[$key]);
            }
        }

        Notification::make()->title(__('تم الحفظ'))->success()->send();
    }

    /** Legacy maintenance → backup: download the office's data as a gzip'd SQL file. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')
                ->label(__('تنزيل نسخة احتياطية'))
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedCircleStack)
                ->color('gray')
                ->visible(fn () => auth()->user()?->is_super_admin || auth()->user()?->can('backups.download'))
                ->action(function () {
                    $path = tempnam(sys_get_temp_dir(), 'rowad-backup-');
                    app(\App\Services\Backups\OfficeBackup::class)->write($path);
                    activity()->event('backup')->log('backup downloaded');

                    return response()->download($path, 'backup-'.now()->format('Y-m-d-His').'.sql.gz', ['Content-Type' => 'application/gzip'])->deleteFileAfterSend();
                }),
        ];
    }
}
