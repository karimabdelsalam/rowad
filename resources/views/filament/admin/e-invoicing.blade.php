@php
    $tone = fn ($s) => ['reported' => 'ok', 'cleared' => 'ok', 'pending' => 'warn', 'failed' => 'warn', 'rejected' => 'bad'][$s] ?? 'muted';
@endphp
<x-filament-panels::page>
    <div class="rowad-einv">
        @unless ($allowed)
            <div class="rowad-plan-notice rowad-plan-notice--warning">{{ __('الفوترة الإلكترونية غير مشمولة في باقتك الحالية. اطلب الترقية من صفحة الاشتراك.') }}</div>
        @endunless

        <div class="rowad-einv__hero is-{{ $ready ? 'ok' : 'off' }}">
            <div class="rowad-einv__state">
                <span class="rowad-einv__dot"></span>
                <div>
                    <p class="rowad-einv__kicker">{{ __('حالة الربط') }}</p>
                    <h2>{{ $ready ? __('مرتبطة بمنصة فاتورة') : ($device ? __('الربط غير مكتمل') : __('غير مرتبطة — المرحلة الأولى (رمز QR فقط)')) }}</h2>
                    @if ($device)
                        <p class="rowad-einv__meta">{{ $device->environmentLabel() }} · {{ __('آخر عدّاد :n', ['n' => $device->counter]) }}
                            @if ($device->certificate_expires_at) · {{ __('الشهادة حتى :d', ['d' => $device->certificate_expires_at->format('Y-m-d')]) }}@endif
                        </p>
                    @endif
                </div>
            </div>
            <div class="rowad-einv__counts">
                @foreach (['cleared', 'reported', 'pending', 'failed', 'rejected'] as $s)
                    <div class="is-{{ $tone($s) }}"><b>{{ number_format($counts[$s] ?? 0) }}</b><span>{{ __($statuses[$s]) }}</span></div>
                @endforeach
            </div>
        </div>

        <div class="rowad-einv__grid">
            <section class="rowad-einv__card">
                <h3>{{ __('جاهزية بيانات المنشأة') }}</h3>
                @if ($problems === [])
                    <p class="rowad-einv__ok">✓ {{ __('بيانات البائع مكتملة: الاسم، الرقم الضريبي، السجل التجاري والعنوان الوطني.') }}</p>
                @else
                    <p>{{ __('أكمل هذه البيانات من الإعدادات العامة قبل الربط:') }}</p>
                    <ul class="rowad-einv__list">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                    <a class="rowad-einv__link" href="{{ \App\Filament\Admin\Pages\GeneralSettings::getUrl() }}">{{ __('فتح الإعدادات العامة') }} ←</a>
                @endif
            </section>
            <section class="rowad-einv__card">
                <h3>{{ __('كيف تعمل') }}</h3>
                <ol class="rowad-einv__steps">
                    <li>{{ __('تُصدر الفاتورة من النظام كالمعتاد (من الأقساط أو يدويًا).') }}</li>
                    <li>{{ __('تُوقَّع رقميًا فورًا بشهادة المؤسسة وتُربط بسلسلة الفواتير السابقة (العدّاد والبصمة).') }}</li>
                    <li>{{ __('المبسطة (للأفراد) تُبلَّغ لزاتكا خلال دقائق؛ الضريبية (للمنشآت) تُعتمد من زاتكا ويُطبع رمزها المعتمد.') }}</li>
                    <li>{{ __('أي فاتورة متعثرة تُعاد محاولتها كل 15 دقيقة، والمرفوضة تُعالج بإشعار دائن.') }}</li>
                </ol>
            </section>
        </div>

        @if ($recent->isNotEmpty())
            <section class="rowad-einv__card">
                <h3>{{ __('آخر المستندات المرسلة') }}</h3>
                <table class="rowad-einv__table">
                    <thead><tr><th>{{ __('العدّاد') }}</th><th>{{ __('الرقم') }}</th><th>{{ __('النوع') }}</th><th>{{ __('الإجمالي') }}</th><th>{{ __('الحالة') }}</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $inv)
                            <tr>
                                <td>{{ $inv->zatca_icv }}</td>
                                <td><a href="{{ \App\Filament\Admin\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $inv]) }}">{{ $inv->number }}</a></td>
                                <td>{{ ['credit' => __('إشعار دائن'), 'debit' => __('إشعار مدين')][$inv->kind] ?? ($inv->type === 'standard' ? __('ضريبية') : __('مبسطة')) }}</td>
                                <td>{{ number_format((float) $inv->total, 2) }}</td>
                                <td><span class="rowad-einv__badge is-{{ $tone($inv->zatca_status) }}">{{ __($statuses[$inv->zatca_status] ?? $inv->zatca_status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</x-filament-panels::page>
