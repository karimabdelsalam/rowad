<?php

namespace App\Filament\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\Owner;
use App\Services\Portal\PortalAccounts;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Table actions on owners/clients: issue portal credentials, sign in as the user. */
trait PortalAccountActions
{
    protected static function portalAccountAction(string $permissionPrefix): Action
    {
        return Action::make('portalAccount')
            ->label(fn (Owner|Client $record) => $record->user_id ? __('إعادة تعيين بيانات الدخول') : __('إنشاء حساب دخول للبوابة'))
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn () => self::portalAllowed("{$permissionPrefix}.reset_credentials"))
            ->fillForm(fn (Owner|Client $record) => ['login' => $record->user?->email ?? $record->user?->username ?? $record->email])
            ->schema([
                TextInput::make('login')->label(__('البريد الإلكتروني أو اسم المستخدم'))->required()->maxLength(150),
                TextInput::make('password')->label(__('كلمة المرور'))->password()->revealable()->minLength(8)
                    ->helperText(__('اتركها فارغة لتوليد كلمة مرور تلقائيًا')),
            ])
            ->action(function (Owner|Client $record, array $data) {
                try {
                    $issued = app(PortalAccounts::class)->issue($record, $data['login'], $data['password'] ?: null);
                    Notification::make()
                        ->title(__('تم حفظ بيانات الدخول'))
                        ->body(__('اسم المستخدم').": {$issued['login']}\n".__('كلمة المرور').": {$issued['password']}")
                        ->success()->persistent()->send();
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected static function loginAsAction(string $permissionPrefix, string $panel): Action
    {
        return Action::make('loginAs')
            ->label(__('الدخول بحسابه'))
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->visible(fn (Owner|Client $record) => $record->user_id !== null && self::portalAllowed("{$permissionPrefix}.login_as"))
            ->url(fn (Owner|Client $record) => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'portal.impersonate', now()->addMinutes(2), ['user' => $record->user_id, 'panel' => $panel]))
            ->openUrlInNewTab();
    }

    private static function portalAllowed(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can($permission));
    }
}
