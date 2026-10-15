@if (session('inquiry_sent'))
    <div class="alert">{{ __('تم استلام رسالتك، سنتواصل معك قريبًا.') }}</div>
@endif
<form method="post" action="{{ $action }}" class="inquiry" style="display:grid;gap:12px">
    @csrf
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;opacity:0" aria-hidden="true">
    <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('الاسم') }}" required maxlength="100">
    <input type="tel" name="mobile" value="{{ old('mobile') }}" placeholder="{{ __('الجوال') }}" dir="ltr" maxlength="30">
    <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('البريد الإلكتروني') }}" dir="ltr" maxlength="150">
    <textarea name="message" rows="4" placeholder="{{ __('رسالتك') }}" required maxlength="2000">{{ old('message', $default ?? '') }}</textarea>
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <button class="btn btn-primary" type="submit">{{ __('إرسال') }}</button>
</form>
