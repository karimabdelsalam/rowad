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
        $this->form->fill(array_merge(PlatformSettings::DEFAULTS, PlatformSettings::all()));
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
                Tab::make(__('إشعار للمؤسسات'))->icon(Heroicon::OutlinedMegaphone)->columns(2)->schema([
                    Toggle::make('announcement_enabled')->label(__('عرض الإشعار في كل لوحات المؤسسات'))->live()->columnSpanFull(),
                    Select::make('announcement_level')->label(__('النوع'))->options(['info' => __('معلومة'), 'warning' => __('تنبيه'), 'danger' => __('مهم')])->default('info')
                        ->visible(fn (Get $get) => (bool) $get('announcement_enabled')),
                    Textarea::make('announcement_text')->label(__('النص'))->rows(2)->columnSpanFull()->maxLength(300)
                        ->visible(fn (Get $get) => (bool) $get('announcement_enabled'))
                        ->placeholder(__('صيانة مجدولة الليلة من 2 إلى 3 صباحًا — قد تتوقف الخدمة لدقائق.')),
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

    public function save(): void
    {
        PlatformSettings::save($this->form->getState());
        Notification::make()->title(__('تم الحفظ'))->success()->send();
    }

    /** What the server is configured with — read only; the full check is on the system health page. */
    private function statusText(): \Illuminate\Support\HtmlString
    {
        $rows = [
            [__('النطاق الأساسي'), config('rowad.tenancy.base_domain')],
            [__('نطاقات المنصة'), implode('، ', config('tenancy.central_domains'))],
            [__('البريد'), config('mail.default').' — '.(config('mail.from.address') ?: '-')],
            [__('الذكاء الاصطناعي'), ClaudeClient::configured() ? __('مفعّل').' ('.config('rowad.ai.model').')' : __('غير مفعّل')],
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
