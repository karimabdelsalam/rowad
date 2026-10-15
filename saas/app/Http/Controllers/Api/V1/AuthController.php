<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\SmsAuthentication;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('تم تسجيل الخروج')]);
    }
}
