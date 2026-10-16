<x-filament-panels::page>
    <div class="rowad-record" style="margin-bottom:16px">
        <div class="rowad-record__main">
            <div class="rowad-record__icon"><x-filament::icon :icon="$failed ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-badge'" /></div>
            <div>
                <p class="rowad-record__kicker">{{ __('فحص الجاهزية') }}</p>
                <h2 class="rowad-record__title">{{ $failed ? trans_choice('{1} مشكلة واحدة يجب إصلاحها|[2,*] :count مشاكل يجب إصلاحها', $failed) : ($warned ? __('جاهز، مع تنبيهات') : __('كل شيء جاهز')) }}</h2>
                <p class="rowad-record__sub"><span>{{ trans_choice('{0} لا توجد تنبيهات|{1} تنبيه واحد|[2,*] :count تنبيهات', $warned) }}</span>@if ($checkedAt)<span>{{ __('آخر فحص كامل: :at', ['at' => $checkedAt]) }}</span>@endif</p>
            </div>
        </div>
        <div class="rowad-record__facts">
            @foreach ($facts as $fact)
                <div class="rowad-record__fact {{ $fact['tone'] ? 'is-'.$fact['tone'] : '' }}"><span>{{ $fact['label'] }}</span><b>{{ $fact['value'] }}</b></div>
            @endforeach
        </div>
    </div>

    <x-filament::section :heading="__('الفحوصات')" :description="__('نفس تقرير الأمر rowad:doctor؛ فحص قواعد كل المؤسسات يعمل عند «إعادة الفحص الكامل» فقط.')">
        <div class="rowad-health">
            @foreach ($rows as [$state, $check, $detail])
                <div class="rowad-health__row is-{{ $state === '✓' ? 'ok' : ($state === '!' ? 'warn' : 'fail') }}">
                    <span class="rowad-health__mark">{{ $state }}</span>
                    <b>{{ $check }}</b>
                    <span class="rowad-health__detail">{{ $detail }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
