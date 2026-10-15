<x-filament-widgets::widget>
    <style>
        .ai-ins { position: relative; border-radius: 20px; padding: 2px; background: linear-gradient(135deg, #5eead4, #0f766e 45%, #134e4a); box-shadow: 0 10px 28px rgb(15 118 110 / .14); }
        .ai-ins__in { border-radius: 18px; background: #fff; padding: 20px 24px; }
        .dark .ai-ins__in { background: rgb(17 24 39); }
        .ai-ins__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
        .ai-ins__title { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.05rem; }
        .ai-ins__title .mark { width: 34px; height: 34px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .ai-ins__title .mark svg { width: 18px; height: 18px; }
        .ai-ins__title small { display: block; font-weight: 500; font-size: .75rem; color: var(--rowad-muted); }
        .ai-ins__actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .ai-ins__btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 10px; font-size: .85rem; font-weight: 700; border: 1px solid var(--rowad-line); }
        .ai-ins__btn--primary { background: var(--rowad-brand); color: #fff; border-color: transparent; }
        .ai-ins__btn[disabled] { opacity: .6; }
        .ai-ins__body { columns: 2 340px; column-gap: 32px; }
        .ai-ins__body ul, .ai-ins__body ol { margin: 0 0 8px; padding-inline-start: 20px; list-style: disc; } .ai-ins__body ol { list-style: decimal; } .ai-ins__body li { margin-bottom: 6px; break-inside: avoid; }
        .ai-ins__body h1, .ai-ins__body h2, .ai-ins__body h3 { font-size: .95rem; font-weight: 800; margin: 6px 0; break-after: avoid; }
        .ai-ins__body p { margin: 0 0 8px; } .ai-ins__body strong { font-weight: 800; }
        .ai-ins__empty { color: var(--rowad-muted); margin: 0; }
    </style>
    @php $sparkle = 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z'; @endphp
    <div class="ai-ins">
        <div class="ai-ins__in">
            <div class="ai-ins__head">
                <div class="ai-ins__title">
                    <span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sparkle }}"/></svg></span>
                    <span>{{ __('رؤى ذكية') }}
                        <small>{{ $insight ? __('آخر تحديث :time', ['time' => $insight->created_at->format('Y-m-d H:i')]) : __('ملخص يومي بالذكاء الاصطناعي لأرقام مؤسستك') }}</small>
                    </span>
                </div>
                <div class="ai-ins__actions">
                    @if ($insight)
                        <a class="ai-ins__btn" href="{{ $chatUrl }}">{{ __('ناقش في المساعد') }}</a>
                    @endif
                    <button type="button" class="ai-ins__btn ai-ins__btn--primary" wire:click="generate" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="generate">{{ $insight ? ($isToday ? __('تحديث الرؤى') : __('رؤى اليوم')) : __('توليد الرؤى') }}</span>
                        <span wire:loading wire:target="generate">{{ __('أحلّل أرقامك…') }}</span>
                    </button>
                </div>
            </div>
            @if ($insight)
                <div class="ai-ins__body ai-md">{!! \App\Filament\Pages\AssistantPage::markdown($insight->content) !!}</div>
            @else
                <p class="ai-ins__empty">{{ __('اضغط "توليد الرؤى" ليقرأ المساعد التحصيل والمتأخرات والإشغال والعقود القريبة من الانتهاء، ويكتب لك أهم الملاحظات وأولويات اليوم.') }}</p>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
