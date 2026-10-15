<x-filament-panels::page>
    <style>
        .ai-shell { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
        @media (max-width: 1024px) { .ai-shell { grid-template-columns: 1fr; } .ai-history { order: 2; } }
        .ai-card { background: #fff; border: 1px solid var(--rowad-line); border-radius: 18px; box-shadow: 0 1px 2px rgb(15 23 42 / .04); }
        .dark .ai-card { background: rgb(255 255 255 / .04); border-color: rgb(255 255 255 / .1); }
        .ai-history { padding: 14px; position: sticky; top: 80px; }
        .ai-new { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; border-radius: 12px; font-weight: 700;
            background: var(--rowad-brand); color: #fff; }
        .ai-new:hover { background: var(--rowad-brand-dark); }
        .ai-history h4 { margin: 16px 6px 8px; font-size: .75rem; font-weight: 700; color: var(--rowad-muted); }
        .ai-history ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; max-height: 60vh; overflow: auto; }
        .ai-history li { display: flex; align-items: center; border-radius: 10px; }
        .ai-history li button.ai-open { flex: 1; text-align: start; padding: 8px 10px; font-size: .88rem; min-width: 0; }
        .ai-history li button.ai-open span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ai-history li button.ai-open small { color: var(--rowad-muted); font-size: .72rem; }
        .ai-history li:hover, .ai-history li.active { background: rgb(15 118 110 / .08); }
        .ai-history li.active button.ai-open span { font-weight: 700; color: var(--rowad-brand-dark); }
        .ai-del { opacity: 0; padding: 6px; color: var(--rowad-muted); } .ai-history li:hover .ai-del { opacity: 1; }
        .ai-del:hover { color: #dc2626; }
        .ai-chat { display: flex; flex-direction: column; min-height: 72vh; overflow: hidden; }
        .ai-messages { flex: 1; padding: 24px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; max-height: 68vh; }
        .ai-hero { margin: auto; text-align: center; max-width: 720px; padding: 24px 8px; }
        .ai-mark { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 20px; display: grid; place-items: center; color: #fff;
            background: radial-gradient(circle at 30% 20%, #5eead4, transparent 55%), linear-gradient(135deg, #14b8a6, #0f766e 55%, #134e4a); box-shadow: 0 12px 30px rgb(15 118 110 / .3); }
        .ai-mark svg { width: 32px; height: 32px; }
        .ai-hero h2 { font-size: 1.5rem; font-weight: 800; margin: 0 0 6px; }
        .ai-hero p { color: var(--rowad-muted); margin: 0 0 22px; }
        .ai-suggest { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; text-align: start; }
        .ai-suggest button { padding: 14px 16px; border: 1px solid var(--rowad-line); border-radius: 14px; background: var(--rowad-surface); font-size: .9rem; font-weight: 600;
            display: flex; gap: 10px; align-items: flex-start; transition: border-color .15s, background .15s; }
        .ai-suggest button:hover { border-color: var(--rowad-brand); background: rgb(15 118 110 / .05); }
        .ai-suggest svg { width: 18px; height: 18px; flex: none; color: var(--rowad-brand); margin-top: 2px; }
        .dark .ai-suggest button { background: rgb(255 255 255 / .03); border-color: rgb(255 255 255 / .1); }
        .ai-msg { display: flex; gap: 12px; align-items: flex-start; }
        .ai-msg--user { justify-content: flex-end; }
        .ai-bubble { max-width: 78%; padding: 12px 16px; border-radius: 18px; background: var(--rowad-brand); color: #fff; white-space: pre-wrap; line-height: 1.8; }
        .ai-msg--user .ai-bubble { border-end-end-radius: 6px; }
        .ai-avatar { width: 34px; height: 34px; flex: none; border-radius: 11px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .ai-avatar svg { width: 18px; height: 18px; }
        .ai-answer { flex: 1; min-width: 0; max-width: 92%; }
        .ai-md { line-height: 1.9; font-size: .95rem; overflow-wrap: anywhere; }
        .ai-md > *:first-child { margin-top: 0; } .ai-md > *:last-child { margin-bottom: 0; }
        .ai-md p { margin: 0 0 10px; } .ai-md ul, .ai-md ol { margin: 0 0 10px; padding-inline-start: 22px; } .ai-md ul { list-style: disc; } .ai-md ol { list-style: decimal; }
        .ai-md h1, .ai-md h2, .ai-md h3 { font-weight: 800; margin: 14px 0 8px; font-size: 1.05rem; }
        .ai-md strong { font-weight: 800; }
        .ai-md table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: .88rem; display: block; overflow-x: auto; }
        .ai-md th { background: var(--rowad-surface); font-weight: 700; text-align: start; }
        .ai-md th, .ai-md td { border: 1px solid var(--rowad-line); padding: 7px 10px; white-space: nowrap; }
        .dark .ai-md th { background: rgb(255 255 255 / .05); } .dark .ai-md th, .dark .ai-md td { border-color: rgb(255 255 255 / .1); }
        .ai-md code { background: var(--rowad-surface); padding: 1px 6px; border-radius: 6px; font-size: .85em; }
        .ai-sources { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; align-items: center; font-size: .75rem; color: var(--rowad-muted); }
        .ai-sources span.chip { background: var(--rowad-surface); border: 1px solid var(--rowad-line); border-radius: 999px; padding: 2px 9px; }
        .dark .ai-sources span.chip { background: rgb(255 255 255 / .04); border-color: rgb(255 255 255 / .1); }
        .ai-typing { display: inline-flex; gap: 5px; padding: 12px 4px; align-items: center; color: var(--rowad-muted); font-size: .88rem; }
        .ai-typing i { width: 7px; height: 7px; border-radius: 50%; background: var(--rowad-brand); animation: ai-dot 1.2s infinite ease-in-out; }
        .ai-typing i:nth-child(2) { animation-delay: .15s; } .ai-typing i:nth-child(3) { animation-delay: .3s; margin-inline-end: 8px; }
        @keyframes ai-dot { 0%, 80%, 100% { opacity: .25; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
        .ai-error { border-radius: 12px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-weight: 600; font-size: .9rem; }
        .ai-composer { border-top: 1px solid var(--rowad-line); padding: 14px 16px 12px; }
        .dark .ai-composer { border-color: rgb(255 255 255 / .1); }
        .ai-input { display: flex; gap: 10px; align-items: flex-end; border: 1px solid #cbd5e1; border-radius: 16px; padding: 8px 8px 8px 8px; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .ai-input:focus-within { border-color: var(--rowad-brand); box-shadow: 0 0 0 4px rgb(15 118 110 / .12); }
        .dark .ai-input { background: transparent; border-color: rgb(255 255 255 / .15); }
        .ai-input textarea { flex: 1; border: 0; resize: none; background: transparent; padding: 6px 8px; font: inherit; max-height: 180px; outline: none; box-shadow: none; }
        .ai-send { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: var(--rowad-brand); color: #fff; flex: none; }
        .ai-send:disabled { opacity: .45; }
        .ai-send svg { width: 20px; height: 20px; }
        [dir=rtl] .ai-send svg { transform: scaleX(-1); }
        .ai-foot { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 8px; font-size: .75rem; color: var(--rowad-muted); }
        .ai-notice { margin-top: 18px; padding: 12px 16px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: .9rem; font-weight: 600; }
    </style>

    @php
        $sparkle = 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z';
    @endphp

    <div class="ai-shell">
        <aside class="ai-card ai-history">
            <button type="button" class="ai-new" wire:click="newConversation">
                <x-filament::icon icon="heroicon-m-plus" class="h-5 w-5" /> {{ __('محادثة جديدة') }}
            </button>
            @if ($conversations->isNotEmpty())
                <h4>{{ __('المحادثات السابقة') }}</h4>
                <ul>
                    @foreach ($conversations as $c)
                        <li @class(['active' => $c->id === $conversationId]) wire:key="conv-{{ $c->id }}">
                            <button type="button" class="ai-open" wire:click="open({{ $c->id }})">
                                <span>@if (($c->context['type'] ?? null) === 'report')📊 @endif{{ $c->title ?: __('محادثة') }}</span>
                                <small>{{ $c->updated_at->format('Y-m-d') }}</small>
                            </button>
                            <button type="button" class="ai-del" title="{{ __('حذف') }}" wire:click="deleteConversation({{ $c->id }})" wire:confirm="{{ __('حذف هذه المحادثة؟') }}">
                                <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        <section class="ai-card ai-chat"
            x-data="{ scroll() { this.$nextTick(() => { const el = this.$refs.list; if (el) el.scrollTop = el.scrollHeight }) } }"
            x-init="scroll()" x-on:assistant-answered.window="scroll()">
            <div class="ai-messages" x-ref="list">
                @if ($messages->isEmpty() && $pending === null)
                    <div class="ai-hero">
                        <div class="ai-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sparkle }}"/></svg></div>
                        <h2>{{ __('كيف أساعدك اليوم؟') }}</h2>
                        <p>{{ $intro }}</p>
                        <div class="ai-suggest">
                            @foreach ($suggestions as $s)
                                <button type="button" wire:click="send(@js($s))" @disabled(! $configured)>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sparkle }}"/></svg>
                                    <span>{{ $s }}</span>
                                </button>
                            @endforeach
                        </div>
                        @unless ($configured)
                            <div class="ai-notice">{{ __('المساعد الذكي غير مفعّل بعد. يضيف مدير المنصة مفتاح الخدمة لتفعيله.') }}</div>
                        @endunless
                    </div>
                @else
                    @foreach ($messages as $m)
                        @if ($m->role === 'user')
                            <div class="ai-msg ai-msg--user" wire:key="m-{{ $m->id }}"><div class="ai-bubble">{{ $m->content }}</div></div>
                        @else
                            <div class="ai-msg" wire:key="m-{{ $m->id }}">
                                <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sparkle }}"/></svg></div>
                                <div class="ai-answer">
                                    <div class="ai-md">{!! \App\Filament\Pages\AssistantPage::markdown($m->content) !!}</div>
                                    @if ($m->tools_used)
                                        <div class="ai-sources">
                                            <span>{{ __('مصدر الإجابة:') }}</span>
                                            @foreach ($m->tools_used as $tool)
                                                <span class="chip">{{ \App\Filament\Pages\AssistantPage::toolLabel($tool) }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @if ($pending !== null)
                        <div class="ai-msg ai-msg--user"><div class="ai-bubble">{{ $pending }}</div></div>
                        <div class="ai-msg">
                            <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sparkle }}"/></svg></div>
                            <div class="ai-typing"><i></i><i></i><i></i>{{ __('أراجع بياناتك وأحلّلها…') }}</div>
                        </div>
                    @endif
                @endif
                @if ($error)
                    <div class="ai-error" role="alert">{{ $error }}</div>
                @endif
            </div>

            <form class="ai-composer" wire:submit="send()">
                <div class="ai-input">
                    <textarea wire:model="question" rows="1" maxlength="4000" placeholder="{{ $example }}"
                        x-data x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send($el.value) }"
                        @disabled(! $configured)></textarea>
                    <button type="submit" class="ai-send" title="{{ __('إرسال') }}" @disabled(! $configured || $pending !== null) wire:loading.attr="disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    </button>
                </div>
                <div class="ai-foot">
                    <span>{{ __('المساعد يقرأ بياناتك ويحلّلها فقط، ولا يعدّل أو يرسل شيئًا. راجع الأرقام المهمة قبل الاعتماد عليها.') }}</span>
                    @if ($remaining !== null)
                        <span>{{ __('متبقٍ :count سؤال هذا الشهر', ['count' => number_format($remaining)]) }}</span>
                    @endif
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
