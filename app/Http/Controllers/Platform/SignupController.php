<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\BusinessRuleException;
use App\Models\Plan;
use App\Mail\NewOfficeAlert;
use App\Mail\OfficeWelcome;
use App\Models\Tenant;
use App\Services\Platform\OfficeProvisioner;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/** Public platform pages: marketing home and self-service office signup (free trial on a chosen plan). */
class SignupController
{
    public function home(): View
    {
        return view('platform.home', ['plans' => $this->plans(), 'demoUrl' => self::demoUrl()]);
    }

    /** The public demo office (created by `rowad:demo`), if this platform has one. */
    public static function demoUrl(): ?string
    {
        $domain = 'demo.'.config('rowad.tenancy.base_domain');
        $tenant = \App\Models\Tenant::whereHas('domains', fn ($q) => $q->where('domain', $domain))->first();
        if ($tenant === null || ! $tenant->isDemo()) {
            return null;
        }
        $port = in_array(request()->getPort(), [80, 443], true) ? '' : ':'.request()->getPort();

        return request()->getScheme().'://'.$domain.$port;
    }

    /** The sign-up page on the platform's own domain (linked from offices' pages, e.g. the demo banner). */
    public static function signupUrl(): string
    {
        $port = in_array(request()->getPort(), [80, 443], true) ? '' : ':'.request()->getPort();

        return request()->getScheme().'://'.config('rowad.tenancy.base_domain').$port.'/signup';
    }

    public function create(Request $request): View
    {
        if (! PlatformSettings::signupOpen()) {
            return view('platform.signup-closed', ['message' => PlatformSettings::get('signup_closed_message')]);
        }
        $plans = $this->plans();

        return view('platform.signup', [
            'plans' => $plans,
            'selected' => (int) old('plan_id', $request->integer('plan') ?: PlatformSettings::defaultPlan()?->id),
            'baseDomain' => config('rowad.tenancy.base_domain'),
        ]);
    }

    public function store(Request $request, OfficeProvisioner $provisioner): RedirectResponse
    {
        abort_unless(PlatformSettings::signupOpen(), 403, __('التسجيل مغلق حاليًا'));
        // bots fill every field; people never see this one
        if (filled($request->input('website'))) {
            return redirect()->to(route('central.home', [], false));
        }

        $request->merge(['phone' => preg_replace('/[\s-]/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'subdomain' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'plan_id' => ['required', 'integer', 'exists:plans,id,is_active,1'],
            'admin_name' => ['required', 'string', 'max:190'],
            'admin_email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^(05\d{8}|(\+|00)?9665\d{8})$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ], [
            'subdomain.regex' => __('النطاق الفرعي يجب أن يكون من 3 إلى 40 حرفًا إنجليزيًا أو رقمًا'),
            'phone.regex' => __('أدخل رقم جوال سعودي مثل 05xxxxxxxx'),
        ], [
            'name' => __('اسم المؤسسة'), 'subdomain' => __('رابط المؤسسة'), 'plan_id' => __('الباقة'), 'admin_name' => __('اسمك'),
            'admin_email' => __('البريد الإلكتروني'), 'phone' => __('الجوال'), 'password' => __('كلمة المرور'), 'terms' => __('الشروط'),
        ]);

        try {
            $tenant = $provisioner->provision([
                'name' => $data['name'],
                'subdomain' => $data['subdomain'],
                'plan_id' => $data['plan_id'],
                'phone' => $data['phone'],
                'admin_name' => $data['admin_name'],
                'admin_email' => $data['admin_email'],
                'admin_password' => $data['password'],
                'locale' => app()->getLocale(),
            ]);
        } catch (BusinessRuleException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['subdomain' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Office signup failed', ['subdomain' => $data['subdomain'], 'error' => $e->getMessage()]);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['name' => __('تعذر إنشاء حساب المؤسسة الآن، حاول مرة أخرى أو تواصل معنا')]);
        }

        $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();
        $office = [
            'name' => $tenant->name,
            'url' => $request->getScheme().'://'.$tenant->domains()->value('domain').$port,
            'email' => $data['admin_email'],
            'trial_ends_at' => $tenant->trial_ends_at?->format('Y-m-d'),
            'plan' => $tenant->plan ? (app()->getLocale() === 'en' ? $tenant->plan->name_en : $tenant->plan->name_ar) : null,
        ];
        self::announce($tenant, $office, $data);

        return redirect()->to(route('central.signup.done', [], false))->with('office', $office);
    }

    /** Welcome the new admin and tell the platform team; mail trouble must never undo a finished signup. */
    private static function announce(Tenant $tenant, array $office, array $data): void
    {
        try {
            Mail::to($data['admin_email'], $data['admin_name'])->locale(app()->getLocale())
                ->queue(new OfficeWelcome($office, $data['admin_name']));
            if ($alerts = PlatformSettings::contact('email')) {
                Mail::to($alerts)->queue(new NewOfficeAlert([
                    'name' => $tenant->name,
                    'subdomain' => $data['subdomain'],
                    'url' => $office['url'],
                    'plan' => $office['plan'],
                    'admin_name' => $data['admin_name'],
                    'admin_email' => $data['admin_email'],
                    'phone' => $data['phone'],
                    'trial_ends_at' => $office['trial_ends_at'],
                    'console_url' => \App\Filament\Platform\Resources\Tenants\TenantResource::getUrl('view', ['record' => $tenant], panel: 'platform'),
                ]));
            }
        } catch (Throwable $e) {
            Log::warning('Signup mail not sent', ['office' => $tenant->name, 'error' => $e->getMessage()]);
        }
    }

    public function done(Request $request): View|RedirectResponse
    {
        $office = $request->session()->get('office');

        return $office ? view('platform.signup-done', ['office' => $office]) : redirect()->to(route('central.signup', [], false));
    }

    private function plans()
    {
        return Plan::where('is_active', true)->orderBy('sort_order')->get();
    }
}
