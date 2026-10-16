{{-- Book a viewing: the next days with free slots, then name and mobile. Works without JavaScript (radio buttons). --}}
@if (session('viewing_booked'))
    @php($at = \Carbon\Carbon::parse(session('viewing_booked')))
    <div class="alert">✓ {{ __('تم حجز موعد المعاينة :date الساعة :time، وأرسلنا لك رسالة بالتفاصيل.', ['date' => $at->locale(app()->getLocale())->translatedFormat('l j F'), 'time' => $at->format('H:i')]) }}</div>
@endif
@php($selected = old('slot') ? substr(old('slot'), 0, 10) : array_key_first($slots))
<form method="post" action="{{ route('site.unit.viewing', $unit) }}" class="viewing" data-day="{{ $selected }}">
    @csrf
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
    <div class="viewing__days" role="tablist">
        @foreach ($slots as $date => $times)
            @php($d = \Carbon\Carbon::parse($date)->locale(app()->getLocale()))
            <button type="button" role="tab" @class(['viewing__day', 'is-active' => $date === $selected]) data-day="{{ $date }}">
                <span>{{ $d->isToday() ? __('اليوم') : ($d->isTomorrow() ? __('غدًا') : $d->translatedFormat('l')) }}</span>
                <b>{{ $d->translatedFormat('j M') }}</b>
            </button>
        @endforeach
    </div>
    @foreach ($slots as $date => $times)
        <div class="viewing__times" data-day="{{ $date }}">
            <p class="viewing__heading">{{ \Carbon\Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('l j F') }}</p>
            @foreach ($times as $time)
                <label class="viewing__time">
                    <input type="radio" name="slot" value="{{ $date }} {{ $time }}" required @checked(old('slot') === $date.' '.$time)>
                    <span dir="ltr">{{ $time }}</span>
                </label>
            @endforeach
        </div>
    @endforeach
    <div class="viewing__who">
        <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('الاسم') }}" required maxlength="100">
        <input type="tel" name="mobile" value="{{ old('mobile') }}" placeholder="{{ __('الجوال') }}" dir="ltr" required maxlength="30">
    </div>
    @error('slot')<div class="error">{{ $message }}</div>@enderror
    @error('mobile')<div class="error">{{ $message }}</div>@enderror
    <button class="btn btn-primary" type="submit">{{ __('احجز الموعد') }}</button>
    <p class="viewing__note">{{ __('ستصلك رسالة بتأكيد الموعد وموقع الوحدة، وتذكير صباح يوم المعاينة.') }}</p>
</form>
<script>
(() => {
    // one day at a time once JavaScript runs; without it every day's times stay listed
    document.querySelectorAll('form.viewing').forEach((form) => {
        const show = (day) => {
            form.dataset.day = day;
            form.querySelectorAll('.viewing__day').forEach((b) => b.classList.toggle('is-active', b.dataset.day === day));
            form.querySelectorAll('.viewing__times').forEach((t) => { t.hidden = t.dataset.day !== day; });
        };
        form.classList.add('is-js');
        form.querySelectorAll('.viewing__day').forEach((b) => b.addEventListener('click', () => show(b.dataset.day)));
        show(form.dataset.day);
    });
})();
</script>
