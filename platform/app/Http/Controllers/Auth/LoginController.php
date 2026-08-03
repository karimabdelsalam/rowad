<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Services\ActivityLogger;
use App\Services\LoginThrottle;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private LoginThrottle $throttle,
        private TotpService $totp,
        private ActivityLogger $log,
    ) {
    }

    public function show(Request $request): View
    {
        return view('auth.login', ['awaitingCode' => $request->session()->has('2fa')]);
    }

    public function password(Request $request): RedirectResponse
    {
        if ($wait = $this->throttle->lockedFor($request->ip(), 'login_failed')) {
            return back()->withErrors(['username' => $this->throttle->message($wait)]);
        }

        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = ConsoleUser::where('username', $data['username'])->where('active', true)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            $this->log->record('login_failed', 'user', null, 'محاولة دخول فاشلة: ' . $data['username']);
            sleep(1);

            return back()->withErrors(['username' => 'اسم المستخدم أو كلمة المرور غير صحيحة.']);
        }

        if ($user->hasTwoFactor()) {
            /*
             * كلمة المرور صحيحة لكن الحساب محمي بخطوتين: لا تُفتح جلسة بعد —
             * تُحفظ الهوية مؤقتًا فقط حتى يصل الرمز الصحيح.
             */
            $request->session()->put('2fa', ['id' => $user->id, 'at' => now()->timestamp]);

            return back();
        }

        return $this->signIn($request, $user);
    }

    public function totp(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('2fa');

        // مهلة خمس دقائق لإدخال الرمز، وإلا يُعاد الدخول من أوله
        if (!$pending || now()->timestamp - (int)$pending['at'] > 300) {
            $request->session()->forget('2fa');

            return back()->withErrors(['code' => 'انتهت المهلة — أعد تسجيل الدخول.']);
        }

        if ($wait = $this->throttle->lockedFor($request->ip(), 'totp_failed')) {
            return back()->withErrors(['code' => $this->throttle->message($wait)]);
        }

        $user = ConsoleUser::where('id', $pending['id'])->where('active', true)->first();

        if (!$user || !$this->totp->verify((string)$user->totp_secret, (string)$request->input('code'))) {
            $this->log->record('totp_failed', 'user', $user?->id, 'رمز تحقق خاطئ');
            sleep(1);

            return back()->withErrors(['code' => 'الرمز غير صحيح.']);
        }

        $request->session()->forget('2fa');

        return $this->signIn($request, $user, true);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function signIn(Request $request, ConsoleUser $user, bool $withTotp = false): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $this->log->record('login', 'user', $user->id,
            'دخول' . ($withTotp ? ' (بخطوتين)' : '') . ': ' . $user->name);

        return redirect()->intended(route('dashboard'));
    }
}
