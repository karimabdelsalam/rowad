@php
    $tone = fn ($s) => ['documented' => 'ok', 'awaiting_parties' => 'warn', 'pending' => 'bad', 'not_required' => 'muted'][$s] ?? 'muted';
@endphp
<x-filament-panels::page>
    <div class="rowad-einv">
        <div class="rowad-einv__hero is-{{ $connected ? 'ok' : 'off' }}">
            <div class="rowad-einv__state">
                <span class="rowad-einv__dot"></span>
                <div>
                    <p class="rowad-einv__kicker">{{ __('حالة الربط') }}</p>
                    <h2>{{ $connected ? __('مرتبط بمنصة إيجار') : __('غير مرتبط — التوثيق يدوي من بيانات التوثيق') }}</h2>
                    <p class="rowad-einv__meta">
                        {{ __('الوحدات المربوطة بعقاراتها في إيجار: :n من :total', ['n' => number_format($linkedUnits), 'total' => number_format($units)]) }}
                        @if ($connected && $checkedAt) · {{ __('آخر تحقق :d', ['d' => $checkedAt]) }}@endif
                    </p>
                </div>
            </div>
            <div class="rowad-einv__counts">
                @foreach (['documented', 'awaiting_parties', 'pending', 'not_required'] as $s)
                    <div class="is-{{ $tone($s) }}"><b>{{ number_format($counts[$s] ?? 0) }}</b><span>{{ $statuses[$s] }}</span></div>
                @endforeach
            </div>
        </div>

        <div class="rowad-einv__grid">
            <section class="rowad-einv__card">
                <h3>{{ __('كيف تعمل') }}</h3>
                <ol class="rowad-einv__steps">
                    <li>{{ __('اربط حساب المكتب مرة واحدة بالمفتاح والسر اللذين يصدرهما إيجار لمكتبك.') }}</li>
                    <li>{{ __('من صفحة الوحدة اضغط "ربط بإيجار": يبحث النظام بالصك عن العقار في إيجار ويحفظ رقم الوحدة.') }}</li>
                    <li>{{ __('من صفحة العقد اضغط "إرسال إلى إيجار": يُنشأ العقد ويُضاف العقار والمؤجرون والمستأجرون وعدادات الخدمات ثم يُرسل.') }}</li>
                    <li>{{ __('يوافق الأطراف من تطبيق إيجار؛ يتابع النظام الحالة كل ساعة ويحفظ رقم العقد ونسخته الموثقة PDF.') }}</li>
                </ol>
            </section>
            <section class="rowad-einv__card">
                <h3>{{ __('قبل الإرسال') }}</h3>
                <ul class="rowad-einv__list">
                    <li>{{ __('هوية كل مؤجر ومستأجر ونوعها وتاريخ ميلاده (يتحقق منها إيجار عبر أبشر).') }}</li>
                    <li>{{ __('للمستأجر المنشأة: رقم السجل التجاري وتاريخ قيده.') }}</li>
                    <li>{{ __('عقار مسجل في إيجار بصكه، ووحدة مربوطة به من صفحة الوحدة.') }}</li>
                    <li>{{ __('العقود السكنية فقط في هذه المرحلة؛ التجارية تُوثق من المنصة.') }}</li>
                </ul>
                @unless ($connected)
                    <p class="rowad-einv__meta">{{ __('بدون ربط تبقى "بيانات التوثيق في إيجار" في صفحة العقد متاحة لنسخها يدويًا.') }}</p>
                @endunless
            </section>
        </div>

        @if ($failed->isNotEmpty())
            <section class="rowad-einv__card">
                <h3>{{ __('عقود توقف إرسالها') }}</h3>
                <table class="rowad-einv__table">
                    <thead><tr><th>{{ __('العقد') }}</th><th>{{ __('سبب التوقف') }}</th><th>{{ __('التاريخ') }}</th></tr></thead>
                    <tbody>
                        @foreach ($failed as $c)
                            <tr>
                                <td><a href="{{ $contractUrl($c) }}">{{ $c->number }}</a></td>
                                <td style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($c->ejar_error, 220) }}</td>
                                <td>{{ $c->updated_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($recent->isNotEmpty())
            <section class="rowad-einv__card">
                <h3>{{ __('آخر العقود المرسلة') }}</h3>
                <table class="rowad-einv__table">
                    <thead><tr><th>{{ __('العقد') }}</th><th>{{ __('رقم العقد في إيجار') }}</th><th>{{ __('الحالة في إيجار') }}</th><th>{{ __('آخر مزامنة') }}</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $c)
                            <tr>
                                <td><a href="{{ $contractUrl($c) }}">{{ $c->number }}</a></td>
                                <td>{{ $c->ejar_number ?: '—' }}</td>
                                <td><span class="rowad-einv__badge is-{{ $tone($c->ejar_status?->value) }}">{{ $c->ejar_status?->getLabel() }}</span></td>
                                <td>{{ $c->ejar_synced_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</x-filament-panels::page>
