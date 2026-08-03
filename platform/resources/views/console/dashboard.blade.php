@extends('layouts.console')
@section('title', 'لوحة التحكم')

@section('content')

<div class="stats">
    <div class="stat accent">
        <div class="label">دخل شهري متكرر (MRR)</div>
        <div class="value">{{ money($mrr) }}</div>
    </div>
    <div class="stat">
        <div class="label">تحصيل هذا الشهر</div>
        <div class="value">{{ money($monthCollected) }}</div>
    </div>
    <div @class(['stat', 'bad' => $outstanding > 0.005])>
        <div class="label">مستحق غير محصَّل</div>
        <div class="value">{{ money($outstanding) }}</div>
    </div>
    <div class="stat">
        <div class="label">إجمالي العيادات</div>
        <div class="value">{{ $totalClinics }}</div>
    </div>
</div>

<div class="stats">
    @foreach (\App\Models\Clinic::STATUSES as $key => $label)
        <div class="stat">
            <div class="label">{{ $label }}</div>
            <div class="value">{{ $byStatus[$key] ?? 0 }}</div>
        </div>
    @endforeach
</div>

@if ($pendingPays)
    <div class="alert alert-warning">
        <strong>{{ $pendingPays }}</strong> دفعة بانتظار تأكيدك (تحويلات إنستا باي غالبًا).
        @if (Route::has('payments.index'))
            <a href="{{ route('payments.index', ['status' => 'pending']) }}">راجعها الآن ←</a>
        @endif
    </div>
@endif

<div class="card">
    <div class="card-head">
        <h2>⏳ اشتراكات تحتاج تجديد</h2>
        <span class="muted">خلال {{ $warnDays }} يومًا أو منتهية بالفعل</span>
    </div>

    @if ($expiring->isEmpty())
        <p class="muted">لا يوجد اشتراك قارب على الانتهاء. 👌</p>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>العيادة</th><th>الخطة</th><th>ينتهي في</th><th>المتبقي</th><th></th></tr></thead>
            <tbody>
            @foreach ($expiring as $c)
                @php($d = $c->daysLeft())
                <tr>
                    <td>
                        @if (Route::has('clinics.show'))
                            <a href="{{ route('clinics.show', $c) }}"><strong>{{ $c->name }}</strong></a>
                        @else
                            <strong>{{ $c->name }}</strong>
                        @endif
                        @if ($c->owner_name)<br><small class="muted">{{ $c->owner_name }}</small>@endif
                    </td>
                    <td>{{ $c->plan?->name ?? '—' }}</td>
                    <td class="num">{{ fmt_date($c->expires_at) }}</td>
                    <td class="num">
                        @if ($d < 0)
                            <span class="badge bad">منتهٍ من {{ abs($d) }} يوم</span>
                        @elseif ($d === 0)
                            <span class="badge bad">ينتهي اليوم</span>
                        @else
                            <span class="badge warn">{{ $d }} يوم</span>
                        @endif
                    </td>
                    <td>
                        @if (Route::has('invoices.create'))
                            <a class="btn btn-sm" href="{{ route('invoices.create', ['clinic' => $c->id]) }}">
                                إصدار فاتورة تجديد</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>

@if ($overdue->isNotEmpty())
<div class="card">
    <h2>🔴 فواتير متأخرة</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الفاتورة</th><th>العيادة</th><th>الاستحقاق</th><th>المتبقي</th><th></th></tr></thead>
        <tbody>
        @foreach ($overdue as $i)
            <tr>
                <td dir="ltr">{{ $i->number }}</td>
                <td>
                    @if (Route::has('clinics.show'))
                        <a href="{{ route('clinics.show', $i->clinic) }}">{{ $i->clinic->name }}</a>
                    @else
                        {{ $i->clinic->name }}
                    @endif
                </td>
                <td class="num">
                    {{ fmt_date($i->due_date) }}
                    <span class="badge bad">متأخرة {{ $i->daysOverdue() }} يوم</span>
                </td>
                <td class="num"><strong>{{ money($i->remaining()) }}</strong></td>
                <td>
                    @if (Route::has('invoices.show'))
                        <a class="btn btn-sm" href="{{ route('invoices.show', $i) }}">فتح</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

<div class="grid2">
    <div class="card">
        <h2>💳 آخر المدفوعات</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>العيادة</th><th>المبلغ</th><th>الوسيلة</th><th>الحالة</th></tr></thead>
            <tbody>
            @forelse ($recentPays as $p)
                <tr>
                    <td>{{ $p->clinic->name }}<br><small class="muted">{{ fmt_date($p->pdate) }}</small></td>
                    <td class="num">{{ money($p->amount) }}</td>
                    <td>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</td>
                    <td>
                        <span @class(['badge', 'ok' => $p->status === 'confirmed',
                                      'warn' => $p->status === 'pending', 'bad' => $p->status === 'failed'])>
                            {{ \App\Models\Payment::STATUSES[$p->status] }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">لا توجد مدفوعات بعد.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <h2>📡 عيادات صامتة</h2>
        <p class="muted">مشتركة لكن نسختها لم تتصل منذ 3 أيام أو أكثر — قد تكون متوقفة أو الرابط تغيّر.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>العيادة</th><th>آخر اتصال</th></tr></thead>
            <tbody>
            @forelse ($silent as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td class="num">
                        @if ($s->last_ping_at)
                            {{ fmt_date($s->last_ping_at) }}
                        @else
                            <span class="badge muted">لم تتصل أبدًا</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">كل النسخ متصلة. 👌</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>

@endsection
