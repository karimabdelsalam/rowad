{{-- Ejar data sheet: every field Ejar asks for, with a copy button; empty required fields are flagged --}}
<div class="rowad-ejar" x-data="{ copied: null, copy(text, key) { navigator.clipboard?.writeText(text); this.copied = key; setTimeout(() => this.copied = null, 1200) } }">
    @if ($missing)
        <div class="rowad-ejar__alert">
            <strong>{{ __('ينقص :n من البيانات المطلوبة في إيجار', ['n' => count($missing)]) }}</strong>
            <span>{{ __('أكملها من ملف المالك أو المستأجر أو الوحدة أو الإعدادات قبل البدء في المنصة.') }}</span>
        </div>
    @else
        <div class="rowad-ejar__ok">{{ __('البيانات المطلوبة مكتملة — انسخها إلى منصة إيجار حقلًا حقلًا.') }}</div>
    @endif
    <div class="rowad-ejar__grid">
        @foreach ($groups as $g => $group)
            <section class="rowad-ejar__card">
                <h3>{{ $group['title'] }}</h3>
                <dl>
                    @foreach ($group['rows'] as $r => $row)
                        <div @class(['rowad-ejar__row', 'is-missing' => $row['required'] && blank($row['value'])])>
                            <dt>{{ $row['label'] }}</dt>
                            <dd>
                                @if (filled($row['value']))
                                    <span dir="auto">{{ $row['value'] }}</span>
                                    <button type="button" class="rowad-ejar__copy" x-on:click="copy(@js($row['value']), '{{ $g }}-{{ $r }}')"
                                        :class="{ 'is-done': copied === '{{ $g }}-{{ $r }}' }" title="{{ __('نسخ') }}">
                                        <span x-show="copied !== '{{ $g }}-{{ $r }}'">{{ __('نسخ') }}</span>
                                        <span x-show="copied === '{{ $g }}-{{ $r }}'" x-cloak>✓</span>
                                    </button>
                                @elseif ($row['required'])
                                    <span class="rowad-ejar__need">{{ __('ناقص') }}</span>
                                @else
                                    <span class="rowad-ejar__none">—</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
</div>
