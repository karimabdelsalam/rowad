@extends('platform.layout')

@section('title', __('إنشاء حساب مؤسسة — رواد'))

@section('styles')
    .signup { padding: 56px 0 88px; background: radial-gradient(800px 360px at 90% 0%, rgb(20 184 166 / .12), transparent 60%), var(--soft); }
    .signup .wrap { display: grid; grid-template-columns: 1.35fr 1fr; gap: 32px; align-items: start; }
    .panel { background: #fff; border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--shadow); padding: 32px; }
    .panel h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 6px; }
    .panel .sub { color: var(--muted); margin: 0 0 26px; }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .full { grid-column: 1 / -1; }
    .group-title { grid-column: 1 / -1; font-weight: 800; color: var(--ink); display: flex; align-items: center; gap: 10px; margin-top: 10px; }
    .group-title::after { content: ""; flex: 1; height: 1px; background: var(--line); }
    .domain { display: flex; direction: ltr; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .domain:focus-within { border-color: var(--brand); box-shadow: 0 0 0 4px rgb(15 118 110 / .12); }
    .domain input { border: 0; flex: 1; padding: 12px 14px; font: inherit; min-width: 0; outline: none; }
    .domain span { background: var(--soft); color: var(--muted); padding: 12px 14px; border-inline-start: 1px solid var(--line); white-space: nowrap; }
    .plan-pick { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .plan-pick label { border: 1px solid var(--line); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; flex-direction: column; gap: 2px; transition: border-color .15s, box-shadow .15s; }
    .plan-pick input { position: absolute; opacity: 0; pointer-events: none; }
    .plan-pick b { color: var(--ink); }
    .plan-pick small { color: var(--muted); }
    .plan-pick input:checked + label { border: 2px solid var(--brand); box-shadow: 0 0 0 4px rgb(15 118 110 / .1); padding: 13px; }
    .plan-pick input:focus-visible + label { outline: 2px solid var(--brand); outline-offset: 2px; }
    .terms { display: flex; gap: 10px; align-items: flex-start; font-size: .95rem; }
    .terms input { width: 18px; height: 18px; margin-top: 4px; accent-color: var(--brand); }
    .submit { width: 100%; margin-top: 8px; }
    .side { position: sticky; top: 96px; display: grid; gap: 18px; }
    .side .card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 26px; }
    .side h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 14px; }
    .side ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
    .side li { display: flex; gap: 10px; }
    .side li .icon { color: #fff; background: var(--brand); border-radius: 50%; padding: 3px; width: 22px; height: 22px; margin-top: 3px; }
    .side .dark { background: linear-gradient(135deg, #0f766e, #134e4a); color: #fff; border: 0; }
    .side .dark h3 { color: #fff; }
    .side .dark p { margin: 0; opacity: .88; }
    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 600; }
    .domain.invalid { border-color: #dc2626; }
    .busy { opacity: .7; pointer-events: none; }
    @media (max-width: 960px) { .signup .wrap { grid-template-columns: 1fr; } .side { position: static; } }
    @media (max-width: 640px) { .grid2, .plan-pick { grid-template-columns: 1fr; } .panel { padding: 22px; } }
@endsection

@section('content')
    @php $check = 'm4.5 12.75 6 6 9-13.5'; @endphp
    <section class="signup">
        <div class="container wrap">
            <div class="panel">
                <h1>{{ __('أنشئ حساب مؤسستك') }}</h1>
                <p class="sub">{{ __('تجربة مجانية كاملة المزايا، بدون بطاقة ائتمان. يجهز نظامك فور الإرسال.') }}</p>

                @if ($errors->any())
                    <div class="alert-error" role="alert">{{ __('راجع الحقول المظللة بالأحمر.') }}</div>
                @endif

                <form method="POST" action="{{ route('central.signup.store', [], false) }}" class="grid2" id="signup" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

                    <div class="group-title">{{ __('المؤسسة') }}</div>
                    <div class="field full">
                        <label for="name">{{ __('اسم المؤسسة') }}</label>
                        <input class="input @error('name') invalid @enderror" id="name" name="name" value="{{ old('name') }}" required maxlength="190" placeholder="{{ __('مثال: مؤسسة الرواد العقارية') }}">
                        @error('name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field full">
                        <label for="subdomain">{{ __('رابط المؤسسة') }}</label>
                        <div class="domain @error('subdomain') invalid @enderror">
                            <input id="subdomain" name="subdomain" value="{{ old('subdomain') }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9-]+" placeholder="alrowad" autocomplete="off" spellcheck="false">
                            <span>.{{ $baseDomain }}</span>
                        </div>
                        <span class="hint">{{ __('أحرف إنجليزية وأرقام وشرطة فقط. هذا رابط دخولك وموقع مؤسستك العام.') }}</span>
                        @error('subdomain')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="group-title">{{ __('الباقة') }}</div>
                    <div class="field full">
                        <div class="plan-pick">
                            @foreach ($plans as $plan)
                                <div>
                                    <input type="radio" name="plan_id" id="plan-{{ $plan->id }}" value="{{ $plan->id }}" @checked($selected === $plan->id)>
                                    <label for="plan-{{ $plan->id }}">
                                        <b>{{ app()->getLocale() === 'ar' ? $plan->name_ar : $plan->name_en }}</b>
                                        <small>{{ number_format($plan->monthly_price) }} {{ __('ر.س / شهريًا') }}</small>
                                        <small>{{ __(':days يومًا مجانًا', ['days' => $plan->trial_days]) }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('plan_id')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="group-title">{{ __('حساب المدير') }}</div>
                    <div class="field">
                        <label for="admin_name">{{ __('اسمك') }}</label>
                        <input class="input @error('admin_name') invalid @enderror" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required autocomplete="name">
                        @error('admin_name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">{{ __('الجوال') }}</label>
                        <input class="input @error('phone') invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" placeholder="05xxxxxxxx" dir="ltr">
                        @error('phone')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field full">
                        <label for="admin_email">{{ __('البريد الإلكتروني') }}</label>
                        <input class="input @error('admin_email') invalid @enderror" id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required autocomplete="email" dir="ltr">
                        <span class="hint">{{ __('تستخدمه للدخول إلى لوحة المؤسسة.') }}</span>
                        @error('admin_email')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password">{{ __('كلمة المرور') }}</label>
                        <input class="input @error('password') invalid @enderror" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                        <span class="hint">{{ __('8 أحرف على الأقل، تتضمن حروفًا وأرقامًا.') }}</span>
                        @error('password')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">{{ __('تأكيد كلمة المرور') }}</label>
                        <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>

                    <div class="field full">
                        <label class="terms"><input type="checkbox" name="terms" value="1" @checked(old('terms'))> <span>{{ __('أوافق على شروط الاستخدام وسياسة الخصوصية.') }}</span></label>
                        @error('terms')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="full">
                        <button class="btn btn-primary btn-lg submit" type="submit" data-busy="{{ __('جارٍ تجهيز نظامك...') }}">{{ __('أنشئ حساب المؤسسة') }}</button>
                    </div>
                </form>
            </div>

            <aside class="side">
                <div class="card">
                    <h3>{{ __('ما الذي تحصل عليه فورًا') }}</h3>
                    <ul>
                        @foreach (['لوحة تحكم المؤسسة برابطك الخاص', 'قاعدة بيانات مستقلة لمؤسستك', 'نماذج عقود وسندات وفواتير جاهزة', 'بوابة للملاك وأخرى للمستأجرين', 'موقع عام لعرض وحداتك'] as $line)
                            <li><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $check }}"/></svg>{{ __($line) }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="card dark">
                    <h3>{{ __('تستخدم نظام رواد السابق؟') }}</h3>
                    <p>{{ __('أنشئ حسابك ثم تواصل معنا، وننقل وحداتك وعقودك ودفعاتك وحسابات ملاكك كما هي.') }}</p>
                </div>
            </aside>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        (function () {
            var sub = document.getElementById('subdomain');
            sub.addEventListener('input', function () {
                var clean = sub.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
                if (clean !== sub.value) sub.value = clean;
            });
            document.getElementById('signup').addEventListener('submit', function (e) {
                var button = this.querySelector('[type=submit]');
                button.textContent = button.dataset.busy;
                this.classList.add('busy');
            });
        })();
    </script>
@endsection
