@if (session('inquiry_sent'))
    <div class="alert">{{ __('تم استلام رسالتك، سنتواصل معك قريبًا.') }}</div>
@endif
<form method="post" action="{{ $action }}" class="inquiry">
    @csrf
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
    <div class="two">
        <label class="field"><span>{{ __('الاسم') }}</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"></label>
        <label class="field"><span>{{ __('الجوال') }}</span><input type="tel" name="mobile" value="{{ old('mobile') }}" dir="ltr" maxlength="30" autocomplete="tel" placeholder="05xxxxxxxx"></label>
    </div>
    <label class="field"><span>{{ __('البريد الإلكتروني') }} <small style="font-weight:500">({{ __('اختياري') }})</small></span><input type="email" name="email" value="{{ old('email') }}" dir="ltr" maxlength="150" autocomplete="email"></label>
    <label class="field"><span>{{ __('رسالتك') }}</span><textarea name="message" rows="4" required maxlength="2000">{{ old('message', $default ?? '') }}</textarea></label>
    @if ($errors->any() && ! $errors->has('slot'))<div class="error">{{ $errors->first() }}</div>@endif
    <button class="btn btn-primary btn-block" type="submit">{{ __('إرسال') }}</button>
</form>
