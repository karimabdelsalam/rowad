{{-- Period filter shared by the accounting reports; $slot holds extra fields. --}}
<div class="rowad-acc-bar">
    {{ $slot ?? '' }}
    <label>{{ __('من') }}<input type="date" wire:model.live="from"></label>
    <label>{{ __('إلى') }}<input type="date" wire:model.live="until"></label>
    <div class="rowad-acc-presets">
        <button type="button" wire:click="$set('from', '{{ now()->startOfMonth()->toDateString() }}'); $set('until', '{{ today()->toDateString() }}')">{{ __('هذا الشهر') }}</button>
        <button type="button" wire:click="$set('from', '{{ now()->startOfYear()->toDateString() }}'); $set('until', '{{ today()->toDateString() }}')">{{ __('هذه السنة') }}</button>
        <button type="button" wire:click="$set('from', '{{ now()->subYear()->startOfYear()->toDateString() }}'); $set('until', '{{ now()->subYear()->endOfYear()->toDateString() }}')">{{ __('السنة الماضية') }}</button>
    </div>
</div>
{{-- printed / PDF only: the office letterhead and the report's title and period, as on every printed document --}}
<div class="rowad-acc-print-head">
    {!! app(\App\Services\Documents\DocumentData::class)->letterhead()['header'] !!}
    <div class="rowad-acc-print-title"><span>REPORT</span><h1>{{ $title }}</h1><p>{{ $period }} · {{ __('تاريخ الإصدار') }} {{ now()->format('Y-m-d H:i') }}</p></div>
</div>
