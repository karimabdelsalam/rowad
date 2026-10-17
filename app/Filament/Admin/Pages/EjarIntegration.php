<?php

namespace App\Filament\Admin\Pages;

use App\Enums\EjarStatus;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Models\RentContract;
use App\Models\Unit;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use App\Support\Demo;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Connects this office to Ejar with the office's own ECRS API key and secret (each brokerage office gets
 * its own pair from Ejar), then shows how the office's contracts stand on the platform.
 */
class EjarIntegration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'ejar';

    protected string $view = 'filament.admin.ejar-integration';

    public static function getNavigationLabel(): string
    {
        return __('منصة إيجار');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('الربط مع منصة إيجار');
    }

    public function getSubheading(): ?string
    {
        return __('وثّق عقود الإيجار في إيجار مباشرة من النظام بحساب مكتبك: يُنشأ العقد ويُضاف العقار والأطراف والخدمات ويُرسل لموافقة الأطراف بضغطة واحدة.');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return tenancy()->initialized && $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    protected function getViewData(): array
    {
        $counts = RentContract::query()->whereNotIn('status', ['cancelled'])
            ->selectRaw('ejar_status, count(*) as n')->groupBy('ejar_status')->pluck('n', 'ejar_status')->all();

        return [
            'connected' => EjarApi::connected(),
            'hasKeys' => filled(Settings::get('ejar_api_key')),
            'checkedAt' => Settings::get('ejar_checked_at'),
            'counts' => $counts,
            'linkedUnits' => Unit::query()->whereNotNull('ejar_unit_id')->count(),
            'units' => Unit::query()->count(),
            'statuses' => collect(EjarStatus::cases())->mapWithKeys(fn (EjarStatus $s) => [$s->value => $s->getLabel()])->all(),
            'failed' => RentContract::query()->whereNotNull('ejar_error')->latest('updated_at')->limit(6)->get(['id', 'number', 'ejar_error', 'updated_at']),
            'recent' => RentContract::query()->whereNotNull('ejar_contract_id')->latest('ejar_synced_at')->limit(8)
                ->get(['id', 'number', 'ejar_number', 'ejar_status', 'ejar_state', 'ejar_synced_at']),
            'contractUrl' => fn (RentContract $c) => RentContractResource::getUrl('view', ['record' => $c]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label(fn () => EjarApi::connected() ? __('تعديل بيانات الربط') : __('ربط حساب المكتب'))
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn () => ! Demo::active())
                ->modalHeading(__('ربط المكتب بمنصة إيجار'))
                ->modalDescription(__('اطلب من فريق إيجار تفعيل خدمة "تسجيل العقود من الأنظمة الخارجية" لمكتبك، وسيصلك مفتاح وسر خاصان بالمكتب. ألصقهما هنا؛ يتحقق النظام منهما قبل الحفظ.'))
                ->modalSubmitActionLabel(__('تحقق واحفظ'))
                ->fillForm(fn () => ['ejar_api_key' => Settings::get('ejar_api_key'), 'ejar_base_url' => Settings::get('ejar_base_url')])
                ->schema([
                    TextInput::make('ejar_api_key')->label(__('مفتاح الربط (API Key)'))->required()->maxLength(200)->autocomplete(false),
                    TextInput::make('ejar_api_secret')->label(__('السر (API Secret)'))->password()->revealable()->maxLength(300)
                        ->required(fn () => blank(Settings::get('ejar_api_secret')))
                        ->helperText(fn () => filled(Settings::get('ejar_api_secret')) ? __('محفوظ مشفّرًا. اتركه فارغًا للإبقاء عليه.') : null),
                    TextInput::make('ejar_base_url')->label(__('عنوان الخدمة'))->url()->maxLength(200)->placeholder(EjarApi::DEFAULT_BASE_URL)
                        ->helperText(__('اتركه فارغًا للعنوان الرسمي. يُغيَّر فقط لبيئة التجربة التي يزودك بها فريق إيجار.')),
                ])
                ->action(function (array $data) {
                    $secret = filled($data['ejar_api_secret'] ?? null) ? $data['ejar_api_secret'] : Settings::get('ejar_api_secret');
                    try {
                        (new EjarApi($data['ejar_api_key'], $secret, $data['ejar_base_url'] ?: EjarApi::DEFAULT_BASE_URL))->ping();
                    } catch (EjarException $e) {
                        Notification::make()->danger()->title(__('تعذّر الربط مع إيجار'))
                            ->body(implode("\n", array_slice([$e->getMessage(), ...$e->messages], 0, 5)))->persistent()->send();

                        return;
                    }
                    Settings::set('ejar_api_key', $data['ejar_api_key'], 'ejar');
                    if (filled($data['ejar_api_secret'] ?? null)) {
                        Settings::set('ejar_api_secret', $data['ejar_api_secret'], 'ejar');
                    }
                    Settings::set('ejar_base_url', $data['ejar_base_url'] ?: null, 'ejar');
                    Settings::set('ejar_enabled', '1', 'ejar');
                    Settings::set('ejar_checked_at', now()->toDateTimeString(), 'ejar');
                    Notification::make()->success()->title(__('تم ربط المكتب بمنصة إيجار'))
                        ->body(__('اربط الوحدات بعقاراتها في إيجار، ثم أرسل العقود من صفحة العقد ← منصة إيجار ← "إرسال إلى إيجار".'))->persistent()->send();
                }),
            Action::make('test')
                ->label(__('اختبار الاتصال'))
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->visible(fn () => EjarApi::connected())
                ->action(function () {
                    try {
                        (new EjarApi)->ping();
                        Settings::set('ejar_checked_at', now()->toDateTimeString(), 'ejar');
                        Notification::make()->success()->title(__('الاتصال بمنصة إيجار يعمل'))->send();
                    } catch (EjarException $e) {
                        Notification::make()->danger()->title($e->getMessage())->body(implode("\n", $e->messages))->persistent()->send();
                    }
                }),
            Action::make('disconnect')
                ->label(__('إلغاء الربط'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => ! Demo::active() && Settings::enabled('ejar_enabled'))
                ->requiresConfirmation()
                ->modalDescription(__('يتوقف الإرسال والمزامنة مع إيجار ويُحذف المفتاح والسر. تبقى أرقام العقود الموثقة وروابط الوحدات كما هي.'))
                ->action(function () {
                    foreach (['ejar_enabled', 'ejar_api_key', 'ejar_api_secret'] as $key) {
                        Settings::set($key, null, 'ejar');
                    }
                    Notification::make()->success()->title(__('تم إلغاء الربط مع إيجار'))->send();
                }),
        ];
    }
}
