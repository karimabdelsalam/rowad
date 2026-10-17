<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Nav;
use App\Models\Plan;
use App\Services\Ai\ClaudeClient;
use App\Services\Licensing\LicenseClient;
use App\Support\PlatformSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Platform-wide switches: who may sign up, how subdomains are formed, contact details, and a notice to every office. */
class PlatformSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = Nav::Platform;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('إعدادات المنصة');
    }

    public function getTitle(): string
    {
        return __('إعدادات المنصة');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public function mount(): void
    {
        // the AI key never goes back to the browser: the field stays empty and shows whether one is saved
        $this->form->fill(array_merge(array_diff_key(PlatformSettings::DEFAULTS, array_flip(PlatformSettings::SECRETS)), PlatformSettings::editable(),
            ['ai_provider' => PlatformSettings::aiProvider(), // the provider in effect, also when it only comes from .env
                'widget_enabled' => PlatformSettings::get('widget_enabled') ?? filled(config('rowad.site_widget.widget'))]));
    }

    public function form(Schema $schema): Schema
    {
        $base = (string) config('rowad.tenancy.base_domain');

        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make(__('التسجيل'))->icon(Heroicon::OutlinedUserPlus)->columns(2)->schema([
                    Toggle::make('signup_open')->label(__('التسجيل الذاتي مفتوح'))->live()->columnSpanFull()
                        ->helperText(__('عند الإغلاق تظهر صفحة "التسجيل مغلق" بدل نموذج التسجيل، ويبقى إنشاء المؤسسات من هنا متاحًا.')),
                    Textarea::make('signup_closed_message')->label(__('رسالة الإغلاق'))->rows(2)->columnSpanFull()
                        ->visible(fn (Get $get) => ! $get('signup_open'))->placeholder(__('نستقبل مؤسسات جديدة قريبًا…')),
                    Select::make('default_plan_id')->label(__('الباقة المختارة افتراضيًا'))
                        ->options(fn () => Plan::where('is_active', true)->orderBy('sort_order')->pluck('name_ar', 'id'))
                        ->placeholder(__('الباقة الاحترافية'))->helperText(__('الباقة المحددة مسبقًا في نموذج التسجيل.')),
                    TextInput::make('trial_days')->label(__('أيام التجربة المجانية'))->integer()->minValue(0)->maxValue(365)
                        ->placeholder(__('حسب كل باقة'))->helperText(__('اتركه فارغًا ليأخذ كل اشتراك مدة التجربة المحددة في باقته.')),
                ]),
                Tab::make(__('النطاقات الفرعية'))->icon(Heroicon::OutlinedGlobeAlt)->columns(2)->schema([
                    Text::make(__('كل مؤسسة تحصل على رابط بالشكل: name.:base — تغيير النطاق الأساسي يكون من TENANT_BASE_DOMAIN في ملف .env.', ['base' => $base]))->columnSpanFull(),
                    TextInput::make('subdomain_min_length')->label(__('أقل طول للنطاق الفرعي'))->integer()->minValue(2)->maxValue(20)->required(),
                    Toggle::make('custom_domains_enabled')->label(__('السماح بالنطاقات الخاصة'))->inline(false)
                        ->helperText(__('إضافة نطاق خاص للمؤسسة (مثل alrowad.com.sa) من صفحة المؤسسة.')),
                    TagsInput::make('reserved_subdomains')->label(__('نطاقات محجوزة إضافية'))->columnSpanFull()
                        ->placeholder('shop')->splitKeys(['Enter', ',', ' '])
                        ->helperText(__('محجوزة دائمًا: :list', ['list' => implode('، ', \App\Services\Platform\OfficeProvisioner::RESERVED_SUBDOMAINS)])),
                ]),
                Tab::make(__('التواصل'))->icon(Heroicon::OutlinedPhone)->columns(3)->schema([
                    Text::make(__('تظهر في الصفحة الرئيسية وصفحات الشروط. الفارغ منها يؤخذ من ملف .env (PLATFORM_CONTACT_*).'))->columnSpanFull(),
                    TextInput::make('contact_email')->label(__('البريد الإلكتروني'))->email()->placeholder(config('rowad.contact.email') ?: 'info@aqarnova.com')->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('contact_phone')->label(__('الهاتف'))->tel()->placeholder(config('rowad.contact.phone') ?: '+966 5X XXX XXXX')->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('contact_whatsapp')->label(__('واتساب (أرقام دولية)'))->placeholder(config('rowad.contact.whatsapp') ?: '9665XXXXXXXX')->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Tab::make(__('البريد'))->icon(Heroicon::OutlinedEnvelope)->schema([
                    Text::make(fn () => $this->mailStatus()),
                    Section::make(__('خادم البريد'))->columns(3)->compact()
                        ->description(__('يُرسل منه بريد المنصة (التسجيل، الترحيب، التنبيهات، استعادة كلمة المرور) وبريد كل مؤسسة لم تضبط خادمها الخاص.'))
                        ->schema([
                            Select::make('mail_mailer')->label(__('طريقة الإرسال'))->live()->placeholder(__('حسب إعدادات السيرفر (.env)'))
                                ->options(['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => __('سجل فقط (بدون إرسال — للتجربة)')]),
                            TextInput::make('mail_host')->label(__('الخادم (Host)'))->placeholder('smtp.example.com')->extraInputAttributes(['dir' => 'ltr'])->maxLength(190)
                                ->required(fn (Get $get) => $get('mail_mailer') === 'smtp')->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_port')->label(__('المنفذ (Port)'))->integer()->minValue(1)->maxValue(65535)->placeholder('587')
                                ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            Select::make('mail_encryption')->label(__('التشفير'))->options(['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => __('بدون')])->default('tls')
                                ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_username')->label(__('اسم المستخدم'))->extraInputAttributes(['dir' => 'ltr'])->maxLength(190)->autocomplete('off')
                                ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                            TextInput::make('mail_password')->label(__('كلمة المرور'))->password()->revealable()->autocomplete('new-password')->maxLength(300)
                                ->placeholder(fn () => filled(PlatformSettings::secret('mail_password')) ? __('محفوظة — اتركها فارغة للإبقاء عليها') : null)
                                ->helperText(__('تُحفظ مشفّرة ولا تُعرض مرة أخرى.'))
                                ->visible(fn (Get $get) => $get('mail_mailer') === 'smtp'),
                        ]),
                    Section::make(__('المُرسِل الرئيسي'))->columns(3)->compact()->schema([
                        TextInput::make('mail_from_address')->label(__('بريد الإرسال'))->email()->extraInputAttributes(['dir' => 'ltr'])
                            ->placeholder(config('mail.from.address') ?: 'no-reply@aqarnova.com'),
                        TextInput::make('mail_from_name')->label(__('اسم المُرسِل'))->maxLength(120)->placeholder(__('عقار نوفا')),
                        TextInput::make('mail_reply_to')->label(__('الرد على (Reply-To)'))->email()->extraInputAttributes(['dir' => 'ltr'])
                            ->placeholder('support@aqarnova.com'),
                        TagsInput::make('signup_alert_emails')->label(__('تنبيهات تسجيل المؤسسات الجديدة إلى'))->columnSpanFull()
                            ->placeholder('team@aqarnova.com')->splitKeys(['Enter', ',', ' '])->nestedRecursiveRules(['email'])
                            ->helperText(__('فارغ: يُرسل إلى بريد التواصل.')),
                    ]),
                    Actions::make([
                        Action::make('testMail')->label(__('حفظ وإرسال رسالة تجربة'))->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                            ->schema([TextInput::make('to')->label(__('إلى'))->email()->required()->default(fn () => auth()->user()?->email)])
                            ->action(fn (array $data) => $this->testMail($data['to'])),
                    ]),
                ]),
                Tab::make(__('موقع عقار نوفا'))->icon(Heroicon::OutlinedWindow)->schema([
                    Text::make(__('النصوص تحل محل النص الافتراضي في الموقع الرسمي باللغة المقابلة؛ الحقل الفارغ يُبقي النص الافتراضي.')),
                    Section::make(__('واجهة الصفحة الرئيسية'))->columns(2)->compact()->collapsible()->schema([
                        TextInput::make('site_hero_badge_ar')->label(__('الشارة أعلى العنوان (عربي)'))->maxLength(120)->placeholder(__('منصة سعودية سحابية لإدارة الأملاك — عربي أولًا')),
                        TextInput::make('site_hero_badge_en')->label(__('الشارة أعلى العنوان (إنجليزي)'))->maxLength(120)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('site_hero_title_ar')->label(__('العنوان الرئيسي (عربي)'))->maxLength(120)->placeholder(__('أدر عقاراتك وعقودك وتحصيلك')),
                        TextInput::make('site_hero_title_en')->label(__('العنوان الرئيسي (إنجليزي)'))->maxLength(120)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('site_hero_highlight_ar')->label(__('الجزء الملوّن من العنوان (عربي)'))->maxLength(60)->placeholder(__('من مكان واحد')),
                        TextInput::make('site_hero_highlight_en')->label(__('الجزء الملوّن من العنوان (إنجليزي)'))->maxLength(60)->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('site_hero_lead_ar')->label(__('الوصف تحت العنوان (عربي)'))->rows(3)->maxLength(400),
                        Textarea::make('site_hero_lead_en')->label(__('الوصف تحت العنوان (إنجليزي)'))->rows(3)->maxLength(400)->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('site_footer_about_ar')->label(__('نبذة التذييل (عربي)'))->rows(2)->maxLength(300),
                        Textarea::make('site_footer_about_en')->label(__('نبذة التذييل (إنجليزي)'))->rows(2)->maxLength(300)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                    Section::make(__('محركات البحث والمشاركة'))->columns(2)->compact()->collapsible()->schema([
                        TextInput::make('site_seo_title_ar')->label(__('عنوان الصفحة (عربي)'))->maxLength(70)->placeholder(__('عقار نوفا — نظام إدارة الأملاك للمنشآت العقارية')),
                        TextInput::make('site_seo_title_en')->label(__('عنوان الصفحة (إنجليزي)'))->maxLength(70)->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('site_seo_description_ar')->label(__('الوصف لمحركات البحث (عربي)'))->rows(2)->maxLength(170),
                        Textarea::make('site_seo_description_en')->label(__('الوصف لمحركات البحث (إنجليزي)'))->rows(2)->maxLength(170)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('site_seo_keywords')->label(__('الكلمات المفتاحية'))->maxLength(300)->placeholder(__('إدارة أملاك، نظام عقاري، إيجار')),
                        TextInput::make('site_og_image')->label(__('صورة المشاركة (رابط)'))->url()->maxLength(500)->extraInputAttributes(['dir' => 'ltr'])
                            ->placeholder(asset('images/platform/dashboard.jpg'))->helperText(__('تظهر عند مشاركة الرابط في واتساب وتويتر ولينكدإن (1200×630 مقترح).')),
                    ]),
                    Section::make(__('حسابات التواصل الاجتماعي'))->columns(3)->compact()->collapsible()
                        ->description(__('تظهر أيقوناتها في تذييل الموقع.'))
                        ->schema(collect(PlatformSettings::SOCIAL)->map(fn ($label, $network) => TextInput::make('social_'.$network)->label($label)->url()->maxLength(300)
                            ->extraInputAttributes(['dir' => 'ltr'])->placeholder('https://'))->values()->all()),
                    Section::make(__('التحليلات والتتبع'))->columns(3)->compact()->collapsible()->schema([
                        TextInput::make('analytics_ga4')->label('Google Analytics 4')->placeholder('G-XXXXXXXXXX')->regex('/^G-[A-Z0-9]+$/i')->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('analytics_gtm')->label('Google Tag Manager')->placeholder('GTM-XXXXXXX')->regex('/^GTM-[A-Z0-9]+$/i')->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('analytics_meta_pixel')->label('Meta Pixel')->placeholder('123456789012345')->regex('/^\d{6,20}$/')->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('site_head_code')->label(__('أكواد إضافية في رأس الصفحة'))->rows(3)->columnSpanFull()->maxLength(5000)->extraInputAttributes(['dir' => 'ltr'])
                            ->helperText(__('مثل وسوم التحقق من Google Search Console أو Bing. تُضاف كما هي إلى صفحات الموقع الرسمي فقط.')),
                    ]),
                    Section::make(__('المحادثة المباشرة (ويدجت عارف)'))->columns(3)->compact()->collapsible()->schema([
                        Toggle::make('widget_enabled')->label(__('تفعيل الويدجت'))->inline(false),
                        TextInput::make('widget_id')->label(__('معرّف الويدجت'))->extraInputAttributes(['dir' => 'ltr'])->placeholder(config('rowad.site_widget.widget')),
                        TextInput::make('widget_tenant')->label(__('رقم الحساب'))->extraInputAttributes(['dir' => 'ltr'])->placeholder(config('rowad.site_widget.tenant')),
                        TextInput::make('widget_src')->label(__('رابط السكربت'))->url()->extraInputAttributes(['dir' => 'ltr'])->columnSpan(2)->placeholder(config('rowad.site_widget.src')),
                    ]),
                ]),
                Tab::make(__('إشعار للمؤسسات'))->icon(Heroicon::OutlinedMegaphone)->columns(2)->schema([
                    Toggle::make('announcement_enabled')->label(__('عرض الإشعار في كل لوحات المؤسسات'))->live()->columnSpanFull(),
                    Select::make('announcement_level')->label(__('النوع'))->options(['info' => __('معلومة'), 'warning' => __('تنبيه'), 'danger' => __('مهم')])->default('info')
                        ->visible(fn (Get $get) => (bool) $get('announcement_enabled')),
                    Textarea::make('announcement_text')->label(__('النص'))->rows(2)->columnSpanFull()->maxLength(300)
                        ->visible(fn (Get $get) => (bool) $get('announcement_enabled'))
                        ->placeholder(__('صيانة مجدولة الليلة من 2 إلى 3 صباحًا — قد تتوقف الخدمة لدقائق.')),
                ]),
                Tab::make(__('الذكاء الاصطناعي'))->icon(Heroicon::OutlinedSparkles)->schema([
                    Text::make(fn () => $this->aiStatus()),
                    Select::make('ai_provider')->label(__('المزوّد المستخدم'))->options(ClaudeClient::PROVIDERS)->required()->live()
                        ->default(fn () => PlatformSettings::aiProvider())
                        ->helperText(__('يمكن حفظ مفاتيح أكثر من مزوّد والتبديل بينها في أي وقت؛ يعمل المساعد وقراءة المستندات والكتابة بأي منها.')),
                    ...collect(ClaudeClient::PROVIDERS)->map(fn ($label, $provider) => $this->providerSection($provider, $label))->values()->all(),
                    Actions::make([
                        Action::make('testAi')->label(__('حفظ واختبار الاتصال'))->icon(Heroicon::OutlinedSignal)->color('gray')
                            ->action(fn () => $this->testAi()),
                    ]),
                ]),
                Tab::make(__('الحالة'))->icon(Heroicon::OutlinedServerStack)->schema([
                    Text::make(fn () => $this->statusText())->columnSpanFull(),
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

    public function save(bool $notify = true): void
    {
        $state = $this->form->getState();
        foreach (PlatformSettings::AI_PROVIDERS as $provider => [$key]) {
            if ($state[$key.'_clear'] ?? false) {
                PlatformSettings::setAiApiKey(null, $provider);
            } elseif (filled($state[$key] ?? null)) {
                PlatformSettings::setAiApiKey($state[$key], $provider);
            }
            $this->data[$key] = null;
            $this->data[$key.'_clear'] = false;
        }
        if (filled($state['mail_password'] ?? null)) {
            PlatformSettings::setSecret('mail_password', $state['mail_password']);
        }
        $this->data['mail_password'] = null;
        PlatformSettings::save($state); // secrets are skipped here
        \App\Support\PlatformMail::apply();
        if ($notify) {
            Notification::make()->title(__('تم الحفظ'))->success()->send();
        }
    }

    /** Save, then send the smallest possible request so a wrong key or model shows up now, not in an office. */
    public function testAi(): void
    {
        $this->save(notify: false);
        try {
            $response = app(ClaudeClient::class)->message('Reply with the single word: ok', [['role' => 'user', 'content' => 'ping']], [], 5);
            Notification::make()->success()->title(__('الاتصال ناجح'))
                ->body(__('النموذج :model يعمل.', ['model' => $response['model'] ?? PlatformSettings::aiModel()]))->send();
        } catch (\App\Services\Ai\AiUnavailable $e) {
            Notification::make()->danger()->title(__('فشل الاتصال'))
                ->body($e->getMessage().' '.__('تأكد من المفتاح واسم النموذج.'))->persistent()->send();
        }
    }

    /** Save, then send a short message through the configured server so a wrong setting shows up now. */
    public function testMail(string $to): void
    {
        $this->save(notify: false);
        try {
            \Illuminate\Support\Facades\Mail::raw(__('رسالة تجربة من لوحة تحكم عقار نوفا: إعدادات البريد تعمل.'), fn ($message) => $message->to($to)->subject(__('رسالة تجربة — عقار نوفا')));
            Notification::make()->success()->title(__('أُرسلت رسالة التجربة إلى :to', ['to' => $to]))
                ->body(config('mail.default') === 'log' ? __('طريقة الإرسال "سجل فقط": الرسالة في سجل النظام ولم تُرسل فعليًا.') : null)->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title(__('تعذّر الإرسال'))->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))->persistent()->send();
        }
    }

    private function mailStatus(): \Illuminate\Support\HtmlString
    {
        $from = config('mail.from.address');
        [$tone, $text] = match (true) {
            config('mail.default') === 'log' => ['warn', __('البريد لا يُرسل فعليًا (سجل فقط). اضبط خادم SMTP ليصل البريد للمستخدمين.')],
            blank($from) || str_ends_with((string) $from, 'example.com') => ['warn', __('حدد بريد الإرسال الرئيسي.')],
            default => ['ok', __('يُرسل البريد عبر :mailer من :from', ['mailer' => strtoupper((string) config('mail.default')), 'from' => "\u{2066}".$from."\u{2069}"])],
        };

        return new \Illuminate\Support\HtmlString('<p class="rowad-ai-status is-'.$tone.'">'.e($text).'</p>');
    }

    /** Models suggested per provider (any other model ID the provider supports can be typed). */
    public const AI_MODELS = [
        'anthropic' => ['claude-sonnet-5-5' => 'متوازن — مقترح', 'claude-opus-5-5' => 'الأعلى جودة', 'claude-haiku-5-5' => 'الأسرع والأقل تكلفة'],
        'openai' => ['gpt-5-mini' => 'متوازن — مقترح', 'gpt-5' => 'الأعلى جودة', 'gpt-5-nano' => 'الأسرع والأقل تكلفة'],
        'gemini' => ['gemini-2.5-flash' => 'متوازن — مقترح', 'gemini-2.5-pro' => 'الأعلى جودة', 'gemini-2.5-flash-lite' => 'الأسرع والأقل تكلفة'],
    ];

    /** Where each provider's key comes from (console) and the server variable it falls back to. */
    private const AI_KEY_HELP = [
        'anthropic' => ['console.anthropic.com ← API Keys', 'ANTHROPIC_API_KEY', 'sk-ant-…'],
        'openai' => ['platform.openai.com ← API keys', 'OPENAI_API_KEY', 'sk-…'],
        'gemini' => ['aistudio.google.com ← Get API key', 'GEMINI_API_KEY', 'AIza…'],
    ];

    private function providerSection(string $provider, string $label): Section
    {
        [$key, $model] = PlatformSettings::AI_PROVIDERS[$provider];
        [$where, $env, $sample] = self::AI_KEY_HELP[$provider];

        return Section::make($label)->columns(2)->compact()
            ->description(fn (Get $get) => $get('ai_provider') === $provider ? __('المزوّد المستخدم الآن') : null)
            ->collapsed(fn (Get $get) => $get('ai_provider') !== $provider)->collapsible()
            ->schema([
                TextInput::make($key)->label(__('مفتاح API'))->password()->revealable()->autocomplete('new-password')
                    ->extraInputAttributes(['dir' => 'ltr'])->maxLength(300)
                    ->placeholder(fn () => match (PlatformSettings::aiKeySource($provider)) {
                        'console' => __('مفتاح محفوظ — اتركه فارغًا للإبقاء عليه'),
                        'env' => __('يُستخدم المفتاح المضبوط على السيرفر — أدخل مفتاحًا ليحل محله'),
                        default => $sample,
                    })
                    ->helperText(__('من :where. يُحفظ مشفّرًا ولا يُعرض مرة أخرى.', ['where' => $where])),
                TextInput::make($model)->label(__('النموذج'))->extraInputAttributes(['dir' => 'ltr'])->maxLength(100)
                    ->datalist(array_keys(self::AI_MODELS[$provider]))->placeholder(config(PlatformSettings::AI_PROVIDERS[$provider][3]) ?: array_key_first(self::AI_MODELS[$provider]))
                    ->helperText(fn () => new \Illuminate\Support\HtmlString(collect(self::AI_MODELS[$provider])
                        ->map(fn ($l, $id) => '<bdi dir="ltr"><code>'.e($id).'</code></bdi> '.e(__($l)))->implode('<br>')
                        .'<br>'.e(__('أسماء النماذج تتغير مع الوقت؛ يمكن كتابة أي نموذج يدعمه المزوّد.')))),
                Toggle::make($key.'_clear')->label(__('حذف المفتاح المحفوظ'))->columnSpanFull()
                    ->visible(fn () => PlatformSettings::aiKeySource($provider) === 'console')
                    ->helperText(__('يعود النظام إلى المفتاح المضبوط على السيرفر (:env) إن وُجد.', ['env' => $env])),
            ]);
    }

    private function aiStatus(): \Illuminate\Support\HtmlString
    {
        $provider = PlatformSettings::aiProvider();
        $vars = ['provider' => ClaudeClient::PROVIDERS[$provider], 'model' => "\u{2066}".PlatformSettings::aiModel()."\u{2069}"];
        [$tone, $text] = match (true) {
            ! ClaudeClient::configured() => ['warn', __('المساعد الذكي غير مفعّل: أضف مفتاح :provider والنموذج.', $vars)],
            PlatformSettings::aiKeySource() === 'console' => ['ok', __('مفعّل عبر :provider بالمفتاح المحفوظ هنا — النموذج :model', $vars)],
            default => ['ok', __('مفعّل عبر :provider بالمفتاح المضبوط على السيرفر — النموذج :model', $vars)],
        };

        return new \Illuminate\Support\HtmlString('<p class="rowad-ai-status is-'.$tone.'">'.e($text).'</p>');
    }

    /** What the server is configured with — read only; the full check is on the system health page. */
    private function statusText(): \Illuminate\Support\HtmlString
    {
        $rows = [
            [__('النطاق الأساسي'), config('rowad.tenancy.base_domain')],
            [__('نطاقات المنصة'), implode('، ', config('tenancy.central_domains'))],
            [__('البريد'), config('mail.default').' — '.(config('mail.from.address') ?: '-')],
            [__('الذكاء الاصطناعي'), ClaudeClient::configured() ? __('مفعّل').' ('.ClaudeClient::PROVIDERS[PlatformSettings::aiProvider()].' — '.PlatformSettings::aiModel().')' : __('غير مفعّل')],
            [__('الترخيص'), LicenseClient::selfHosted() ? __('نسخة مثبتة لدى العميل') : __('منصة عقار نوفا (تُصدر التراخيص)')],
            [__('قواعد المؤسسات'), config('tenancy.database.managers.mysql') === \App\Tenancy\CpanelDatabaseManager::class ? 'cPanel' : 'MySQL'],
            [__('الطابور'), config('queue.default').(config('rowad.queue_via_scheduler') ? ' ('.__('عبر المجدول').')' : '')],
        ];
        $html = '<dl class="rowad-status">';
        foreach ($rows as [$k, $v]) {
            $html .= '<div><dt>'.e($k).'</dt><dd>'.e($v).'</dd></div>';
        }

        return new \Illuminate\Support\HtmlString($html.'</dl>');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('health')->label(__('فحص النظام'))->icon(Heroicon::OutlinedHeart)->color('gray')->url(fn () => SystemHealthPage::getUrl()),
        ];
    }
}
