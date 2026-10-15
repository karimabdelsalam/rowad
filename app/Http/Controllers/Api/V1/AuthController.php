<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\PasswordResetByOtp;
use App\Auth\SmsAuthentication;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Token sign-in for the mobile apps (legacy ?output=json + ACCESS-TOKEN).
 * When the office requires SMS login, the first call sends a code (202) and
 * the second call repeats the credentials with that code.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        $throttleKey = 'api-login:'.tenant('id').'|'.mb_strtolower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['login' => __('محاولات كثيرة، حاول بعد :seconds ثانية', ['seconds' => RateLimiter::availableIn($throttleKey)])])->status(429);
        }

        $credentials = [filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $data['login'], 'password' => $data['password']];
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials) || ! $user->is_active) {
            RateLimiter::hit($throttleKey, 300);
            event(new Failed('sanctum', $user, $credentials));

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        if ($user->smsLoginRequired()) {
            $sms = SmsAuthentication::make();
            if (blank($data['code'] ?? null)) {
                $sms->sendCode($user);

                return response()->json(['mfa_required' => true, 'channel' => 'sms', 'message' => __('تم إرسال رمز التحقق إلى جوالك')], 202);
            }
            if (! $sms->verifyCode($data['code'], $user)) {
                RateLimiter::hit($throttleKey, 300);

                throw ValidationException::withMessages(['code' => __('رمز التحقق غير صحيح')]);
            }
        }

        RateLimiter::clear($throttleKey);
        event(new Login('sanctum', $user, false));
        $token = $user->createToken($data['device_name'], [$user->type->value]);

        return response()->json(['token' => $token->plainTextToken, 'user' => new UserResource($user)]);
    }

    /** Legacy resetpwd(mobile): always 202 so the API does not reveal which numbers have accounts. */
    public function requestPasswordCode(Request $request, PasswordResetByOtp $otp): JsonResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20']]);
        $key = 'api-reset:'.tenant('id').'|'.$request->ip();
        if (! RateLimiter::attempt($key, 3, fn () => true, 600)) {
            return response()->json(['message' => __('محاولات كثيرة، حاول بعد :seconds ثانية', ['seconds' => RateLimiter::availableIn($key)])], 429);
        }
        if ($user = $otp->findUser($data['mobile'])) {
            $otp->send($user);
        }

        return response()->json(['message' => __('إذا كان الرقم مرتبطًا بحساب فقد أرسلنا إليه رمز التحقق')], 202);
    }

    /** Legacy resetbyotp. */
    public function resetPassword(Request $request, PasswordResetByOtp $otp): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);
        $user = $otp->findUser($data['mobile']);
        if ($user === null || ! $otp->reset($user, $data['code'], $data['password'])) {
            throw ValidationException::withMessages(['code' => __('رمز التحقق غير صحيح أو انتهت صلاحيته')]);
        }

        return response()->json(['message' => __('تم تحديث كلمة المرور بنجاح')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('تم تسجيل الخروج')]);
    }
}
