<?php

namespace App\Filament\Platform\Pages;

use App\Services\Licensing\LicenseClient;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Self-hosted copies: the license status and activation (on aqarnova.com licenses are issued from "التراخيص"). */
class LicensePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = \App\Filament\Platform\Nav::Billing;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'license';

    protected string $view = 'filament.platform.license-page';

    public static function shouldRegisterNavigation(): bool
    {
        return LicenseClient::selfHosted();
    }

    public static function canAccess(): bool
    {
        return LicenseClient::selfHosted() && (auth()->user()?->is_super_admin ?? false);
    }

    public static function getNavigationLabel(): string
    {
        return __('الترخيص');
    }

    public function getTitle(): string
    {
        return __('الترخيص');
    }

    protected function getViewData(): array
    {
        $client = app(LicenseClient::class);

        return ['state' => $client->state(), 'stored' => $client->stored(), 'domain' => $client->domain(), 'server' => config('rowad.license.server')];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label(__('تفعيل الترخيص'))
                ->icon(Heroicon::OutlinedKey)
                ->schema([TextInput::make('key')->label(__('مفتاح الترخيص'))->required()->placeholder('AQN-XXXXX-XXXXX-XXXXX-XXXXX')
                    ->default(fn () => config('rowad.license.key'))->extraInputAttributes(['dir' => 'ltr'])])
                ->action(fn (array $data) => $this->report(app(LicenseClient::class)->activate(strtoupper(trim($data['key']))), __('تم تفعيل الترخيص'))),
            Action::make('check')
                ->label(__('تحقق الآن'))
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->report(app(LicenseClient::class)->check(), __('الترخيص سليم'))),
            Action::make('deactivate')
                ->label(__('إلغاء التفعيل على هذا السيرفر'))
                ->color('danger')
                ->icon(Heroicon::OutlinedXCircle)
                ->requiresConfirmation()
                ->modalDescription(__('يتوقف النظام على هذا السيرفر حتى يُفعَّل من جديد. استخدمه عند نقل النظام لسيرفر آخر.'))
                ->visible(fn () => filled(app(LicenseClient::class)->stored()['token'] ?? null))
                ->action(fn () => app(LicenseClient::class)->deactivate()),
        ];
    }

    private function report(array $result, string $success): void
    {
        $result['ok']
            ? Notification::make()->title($success)->success()->send()
            : Notification::make()->title(__('لم يتم: :reason', ['reason' => $result['message'] ?? $result['code']]))->danger()->persistent()->send();
    }
}
