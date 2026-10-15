@extends('platform.layout')

@section('title', __('تم إنشاء حساب مؤسستك — رواد'))

@section('styles')
    .done { padding: 72px 0 96px; background: radial-gradient(800px 360px at 50% 0%, rgb(20 184 166 / .14), transparent 60%), var(--soft); }
    .done .card { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid var(--line); border-radius: 24px; box-shadow: var(--shadow); padding: 44px 36px; text-align: center; }
    .done .ok { width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 18px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(135deg, #14b8a6, var(--brand)); box-shadow: 0 12px 30px rgb(15 118 110 / .3); }
    .done .ok svg { width: 36px; height: 36px; }
    .done h1 { font-size: 1.9rem; font-weight: 800; margin-bottom: 8px; }
    .done p { color: var(--muted); margin: 0 0 22px; }
    .done dl { display: grid; gap: 10px; text-align: start; background: var(--soft); border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; margin: 0 0 26px; }
    .done dl div { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .done dt { color: var(--muted); }
    .done dd { margin: 0; font-weight: 700; color: var(--ink); direction: ltr; unicode-bidi: plaintext; }
    .done .next { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
@endsection

@section('content')
    <section class="done">
        <div class="container">
            <div class="card">
                <div class="ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
                <h1>{{ __('مؤسستك جاهزة!') }}</h1>
                <p>{{ __('أنشأنا نظام :name وقاعدة بياناته. ادخل الآن بالبريد وكلمة المرور التي اخترتها.', ['name' => $office['name']]) }}</p>
                <dl>
                    <div><dt>{{ __('رابط لوحة المؤسسة') }}</dt><dd>{{ $office['url'] }}/admin</dd></div>
                    <div><dt>{{ __('البريد الإلكتروني') }}</dt><dd>{{ $office['email'] }}</dd></div>
                    @if ($office['trial_ends_at'])
                        <div><dt>{{ __('تنتهي التجربة المجانية') }}</dt><dd>{{ $office['trial_ends_at'] }}</dd></div>
                    @endif
                </dl>
                <div class="next">
                    <a class="btn btn-primary btn-lg" href="{{ $office['url'] }}/admin/login">{{ __('الدخول إلى لوحة المؤسسة') }}</a>
                    <a class="btn btn-ghost btn-lg" href="{{ $office['url'] }}" target="_blank" rel="noopener">{{ __('موقع المؤسسة العام') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
