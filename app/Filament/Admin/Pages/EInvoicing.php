<?php

namespace App\Filament\Admin\Pages;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\ZatcaDevice;
use App\Services\Zatca\Phase2\FatooraApi;
use App\Services\Zatca\Phase2\Onboarding;
use App\Services\Zatca\Phase2\SellerProfile;
use App\Services\Zatca\Phase2\ZatcaException;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use App\Support\PlanLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** ZATCA integration phase: connect the office's e-invoicing unit and watch reporting/clearance. */
class EInvoicing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'e-invoicing';

    protected string $view = 'filament.admin.e-invoicing';

    public static function getNavigationLabel(): string
    {
        return __('الفوترة الإلكترونية');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    public function getTitle(): string
    {
        return __('الفوترة الإلكترونية — المرحلة الثانية');
    }

    public function getSubheading(): ?string
    {
        return __('ربط المؤسسة بمنصة فاتورة (زاتكا): تُوقَّع كل فاتورة رقميًا وتُرسل تلقائيًا — المبسطة تُبلَّغ والضريبية تُعتمد قبل وصولها للعميل.');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return tenancy()->initialized && $user !== null && ($user->is_super_admin || $user->can('settings.update'));
    }

    protected function getViewData(): array
    {
        $device = ZatcaDevice::query()->latest('id')->first();
        $counts = Invoice::query()->whereNotNull('zatca_status')->where('zatca_status', '!=', '')
            ->selectRaw('zatca_status, count(*) as n')->groupBy('zatca_status')->pluck('n', 'zatca_status')->all();

        return [
            'device' => $device,
            'ready' => $device?->isReady() ?? false,
            'allowed' => PlanLimits::allows('zatca'),
            'problems' => SellerProfile::problems(),
            'counts' => $counts,
            'statuses' => ZatcaInvoicing::STATUSES,
            'recent' => Invoice::query()->whereNotNull('zatca_icv')->latest('zatca_icv')->limit(8)->get(['id', 'number', 'kind', 'type', 'total', 'zatca_icv', 'zatca_status', 'zatca_submitted_at']),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label(fn () => ZatcaDevice::active() ? __('إعادة الربط') : __('ربط بمنصة فاتورة'))
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn () => PlanLimits::allows('zatca'))
                ->modalHeading(__('ربط وحدة الفوترة بمنصة فاتورة'))
                ->modalDescription(__('ادخل بوابة فاتورة (fatoora.zatca.gov.sa) ← "تسجيل وحدة حلول الفوترة الإلكترونية" ← أنشئ رمز OTP، ثم ألصقه هنا خلال ساعة. يُنشئ النظام المفتاح والشهادة ويجتاز فحوص الامتثال الستة تلقائيًا.'))
                ->modalSubmitActionLabel(__('ربط الآن'))
                ->schema([
                    Select::make('environment')->label(__('البيئة'))->options(collect(FatooraApi::ENVIRONMENTS)->map(fn ($l) => __($l))->all())->default('core')->required()
                        ->helperText(__('بيئة المطورين تقبل الرمز 123345 للتجربة؛ المحاكاة والإنتاج تحتاجان رمزًا من بوابة فاتورة.')),
                    TextInput::make('otp')->label(__('رمز التحقق (OTP)'))->required()->length(6)->numeric(),
                ])
                ->action(function (array $data) {
                    try {
                        $device = app(Onboarding::class)->run($data['environment'], $data['otp']);
                        Notification::make()->success()->title(__('تم الربط بنجاح'))
                            ->body(__('اجتازت الوحدة فحوص الامتثال وصدرت شهادة الإنتاج (:env). الفواتير الجديدة تُرسل لزاتكا تلقائيًا.', ['env' => $device->environmentLabel()]))->persistent()->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->warning()->title($e->getMessage())->persistent()->send();
                    } catch (ZatcaException $e) {
                        Notification::make()->danger()->title(__('رفضت منصة فاتورة الربط'))
                            ->body(implode("\n", array_slice([$e->getMessage(), ...$e->messages], 0, 6)))->persistent()->send();
                    }
                }),
        ];
    }
}
