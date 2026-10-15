<?php

namespace App\Filament\Auth;

use App\Auth\PasswordResetByOtp;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

/**
 * Legacy resetpwd: by email (link) or by mobile (SMS code, then a new password on the same page).
 * The response never says whether an account exists for the email or mobile.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public bool $codeSent = false;

    public ?string $mobile = null;

    public function form(Schema $schema): Schema
    {
        if (! $this->codeSent) {
            return $schema->components([$this->getEmailFormComponent()]);
        }

        return $schema->components([
            TextInput::make('code')->label(__('رمز التحقق'))->required()->length(6)->numeric()->autocomplete('one-time-code')->autofocus(),
            TextInput::make('password')->label(__('كلمة المرور الجديدة'))->password()->revealable()->required()->rule(Password::default())->same('password_confirmation'),
            TextInput::make('password_confirmation')->label(__('تأكيد كلمة المرور'))->password()->revealable()->required()->dehydrated(false),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('البريد الإلكتروني أو رقم الجوال'))
            ->required()
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL) && ! PasswordResetByOtp::looksLikeMobile((string) $value)) {
                    $fail(__('أدخل بريدًا إلكترونيًا أو رقم جوال صحيحًا'));
                }
            })
            ->autofocus();
    }

    public function request(): void
    {
        if ($this->codeSent) {
            $this->resetWithCode();

            return;
        }
        if (filter_var($this->data['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
            parent::request();

            return;
        }

        try {
            $this->rateLimit(3);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $mobile = (string) $this->form->getState()['email'];
        $otp = app(PasswordResetByOtp::class);
        $user = $otp->findUser($mobile);
        if ($user !== null && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $otp->send($user);
        }

        $this->mobile = $mobile;
        $this->codeSent = true;
        $this->form->fill();
        Notification::make()->title(__('إذا كان الرقم مرتبطًا بحساب فقد أرسلنا إليه رمز التحقق'))->success()->send();
    }

    private function resetWithCode(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();
        $otp = app(PasswordResetByOtp::class);
        $user = $otp->findUser((string) $this->mobile);

        if ($user === null || ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()) || ! $otp->reset($user, $data['code'], $data['password'])) {
            Notification::make()->title(__('رمز التحقق غير صحيح أو انتهت صلاحيته'))->danger()->send();

            return;
        }

        Notification::make()->title(__('تم تحديث كلمة المرور بنجاح'))->success()->send();
        $this->redirect(Filament::getLoginUrl());
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return ['email' => $data['email']];
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->codeSent ? __('أدخل الرمز المرسل إلى :mobile وكلمة المرور الجديدة', ['mobile' => $this->mobile]) : parent::getSubheading();
    }
}
