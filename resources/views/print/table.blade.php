{{-- Any list or report as a printable sheet on the office letterhead (rendered inside print.document, landscape). --}}
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">REPORT</div>
        <h1>{{ $title }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('تاريخ الإصدار') }}</span><b>{{ now()->format('Y-m-d H:i') }}</b></div>
        <div><span>{{ __('عدد السجلات') }}</span><b>{{ number_format(count($rows)) }}</b></div>
        @if (auth()->user())<div><span>{{ __('أصدره') }}</span><b>{{ auth()->user()->name }}</b></div>@endif
    </div>
</div>
@if ($filters !== [])
    <p class="doc-lead report-filters">
        <span class="muted">{{ __('حسب') }}:</span>
        @foreach ($filters as $filter)<span class="badge">{{ $filter }}</span> @endforeach
    </p>
@endif

<div class="section">
    <table class="data report">
        <thead>
            <tr><th class="idx">#</th>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td class="idx">{{ $i + 1 }}</td>
                    @foreach ($row as $cell)
                        @if (is_float($cell))
                            <td class="num">{{ number_format($cell, 2) }}</td>
                        @elseif (is_int($cell))
                            <td class="num">{{ $cell }}</td>
                        @else
                            <td>{{ $cell }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) + 1 }}" class="muted" style="text-align:center;padding:24px">{{ __('لا توجد بيانات') }}</td></tr>
            @endforelse
        </tbody>
        @if ($totals !== [])
            <tfoot>
                <tr>
                    <td class="idx"></td>
                    @foreach ($totals as $i => $total)
                        @if ($i === 0 && $total === null)
                            <td>{{ __('الإجمالي') }}</td>
                        @else
                            <td class="num">{{ $total === null ? '' : number_format($total, 2) }}</td>
                        @endif
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
    @if ($truncated)
        <p class="muted" style="margin-top:8px">{{ __('تُطبع أول :count صفًا فقط؛ استخدم تصدير Excel للقائمة كاملة.', ['count' => count($rows)]) }}</p>
    @endif
</div>
{!! $footer !!}
