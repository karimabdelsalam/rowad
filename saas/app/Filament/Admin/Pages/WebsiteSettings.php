<?php

namespace App\Filament\Admin\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Office landing page settings (bilingual content). */
class WebsiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 3;

    public const KEYS = [
        'landing_enabled', 'site_name', 'site_name_en', 'landing_hero_title', 'landing_hero_title_en',
        'landing_hero_subtitle', 'landing_hero_subtitle_en', 'landing_about', 'landing_about_en',
        'landing_whatsapp', 'landing_primary_color', 'site_closed_message',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('موقع المكتب');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('موقع المكتب');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $plan = tenancy()->initialized ? tenant()->plan : null;

        return $user !== null && ($user->is_super_admin || $user->can('settings.update'))
            && ($plan === null || $plan->hasFeature('landing_page'));
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Settings::get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('عام'))->columns(2)->schema([
                Toggle::make('landing_enabled')->label(__('تفعيل الموقع العام'))->live()->columnSpanFull(),
                Textarea::make('site_closed_message')->label(__('رسالة إغلاق الموقع'))->rows(2)->columnSpanFull()
                    ->helperText(__('تظهر للزوار بدل الموقع أثناء إغلاقه. اتركها فارغة للتحويل إلى صفحة الدخول.'))
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get('landing_enabled')),
                TextInput::make('site_name')->label(__('اسم المكتب')),
                TextInput::make('site_name_en')->label(__('اسم المكتب بالإنجليزية')),
                TextInput::make('landing_whatsapp')->label(__('رقم واتساب'))->tel()->helperText(__('بالصيغة الدولية مثل 9665xxxxxxxx')),
                ColorPicker::make('landing_primary_color')->label(__('اللون الرئيسي')),
            ]),
            Section::make(__('المحتوى بالعربية'))->schema([
                TextInput::make('landing_hero_title')->label(__('العنوان الرئيسي')),
                TextInput::make('landing_hero_subtitle')->label(__('العنوان الفرعي')),
                Textarea::make('landing_about')->label(__('من نحن'))->rows(4),
            ]),
            Section::make(__('المحتوى بالإنجليزية'))->collapsible()->schema([
                TextInput::make('landing_hero_title_en')->label(__('العنوان الرئيسي')),
                TextInput::make('landing_hero_subtitle_en')->label(__('العنوان الفرعي')),
                Textarea::make('landing_about_en')->label(__('من نحن'))->rows(4),
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
            if (in_array($key, self::KEYS, true)) {
                Settings::set($key, $value, 'website');
            }
        }

        Notification::make()->title(__('تم الحفظ'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('visit')->label(__('عرض الموقع'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url('/', shouldOpenInNewTab: true),
        ];
    }
}
