<x-filament-panels::page>
    <div class="rowad-import">
        <ol class="rowad-import__steps">
            @foreach ($types as $key => $title)
                <li wire:key="step-{{ $key }}" @class(['rowad-import__step', 'is-active' => $type === $key])>
                    <button type="button" class="rowad-import__pick" wire:click="choose('{{ $key }}')">
                        <span class="rowad-import__num">{{ $loop->iteration }}</span>
                        <span class="rowad-import__name">{{ $title }}</span>
                        <span class="rowad-import__count">{{ __('في النظام الآن: :n', ['n' => number_format($counts[$key] ?? 0)]) }}</span>
                    </button>
                    <div class="rowad-import__tpl">{{ ($this->templateAction)(['type' => $key]) }}</div>
                </li>
            @endforeach
        </ol>

        <div class="rowad-import__grid">
            <section class="rowad-einv__card">
                <h3>{{ __('رفع الملف') }} — {{ $types[$type] }}</h3>
                <form wire:submit="check">
                    {{ $this->form }}
                    <div class="rowad-import__buttons">
                        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:target="check,data.upload">{{ __('معاينة الملف') }}</x-filament::button>
                        @if ($preview || $result)
                            <x-filament::button color="gray" wire:click="startOver" type="button">{{ __('البدء من جديد') }}</x-filament::button>
                        @endif
                    </div>
                </form>
            </section>

            <aside class="rowad-einv__card">
                <h3>{{ __('أعمدة القالب') }}</h3>
                <p>{{ __('الأعمدة المعلّمة بنجمة مطلوبة، والباقي اختياري. يمكن ترك أي عمود اختياري فارغًا أو حذفه.') }}</p>
                <ul class="rowad-import__cols">
                    @foreach ($columns as $column)
                        <li @class(['is-required' => $column['required']])>{{ $column['label'] }}@if ($column['required']) *@endif</li>
                    @endforeach
                </ul>
                <p class="rowad-import__tip">
                    @if ($type === 'rent_contracts')
                        {{ __('استورد الوحدات والعملاء قبل العقود. التواريخ بالميلادي (2026-01-31 أو 31/01/2026) أو بالهجري (1447-07-12) فيُنشأ العقد بالتقويم الهجري.') }}
                    @elseif ($type === 'units')
                        {{ __('يُنشأ العقار والمدينة والحي تلقائيًا عند أول ظهور. المالك يُطابق برقم هويته أو يُضاف باسمه.') }}
                    @else
                        {{ __('رقم الهوية والجوال يمنعان تكرار الشخص نفسه عند إعادة رفع الملف.') }}
                    @endif
                </p>
            </aside>
        </div>

        @if ($preview)
            <section class="rowad-einv__card rowad-import__preview">
                <h3>{{ __('معاينة الملف') }}</h3>
                <div class="rowad-import__stats">
                    <div><b>{{ number_format($preview['rows']) }}</b><span>{{ __('صف في الملف') }}</span></div>
                    <div class="is-ok"><b>{{ number_format($preview['ready']) }}</b><span>{{ __('جاهز للاستيراد') }}</span></div>
                    <div @class(['is-bad' => count($preview['problems']) > 0])><b>{{ number_format(count($preview['problems'])) }}</b><span>{{ __('صف به ملاحظات (سيُتجاوز)') }}</span></div>
                </div>

                @if ($preview['missing_headers'] !== [])
                    <div class="rowad-ejar__alert"><strong>{{ __('أعمدة مطلوبة غير موجودة في الملف:') }}</strong> {{ implode('، ', $preview['missing_headers']) }}</div>
                @endif
                @if ($preview['unknown_headers'] !== [])
                    <div class="rowad-import__note">{{ __('أعمدة لا يعرفها القالب وستُتجاهل:') }} {{ implode('، ', $preview['unknown_headers']) }}</div>
                @endif

                @if ($preview['problems'] !== [])
                    <table class="rowad-einv__table">
                        <thead><tr><th style="width: 90px">{{ __('الصف') }}</th><th>{{ __('الملاحظات') }}</th></tr></thead>
                        <tbody>
                            @foreach (array_slice($preview['problems'], 0, 50) as $p)
                                <tr><td>{{ $p['row'] }}</td><td>{{ implode(' · ', $p['problems']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($preview['problems']) > 50)
                        <p class="rowad-import__note">{{ __('و:n صفًا آخر بملاحظات.', ['n' => count($preview['problems']) - 50]) }}</p>
                    @endif
                @endif

                <div class="rowad-import__buttons">
                    <x-filament::button wire:click="runImport" icon="heroicon-o-arrow-down-on-square-stack" :disabled="$preview['ready'] === 0" wire:target="runImport">
                        {{ __('استيراد :n صفًا', ['n' => number_format($preview['ready'])]) }}
                    </x-filament::button>
                </div>
            </section>
        @endif

        @if ($result)
            <section class="rowad-einv__card rowad-import__preview">
                <h3>{{ __('نتيجة الاستيراد') }}</h3>
                <div class="rowad-import__stats">
                    <div class="is-ok"><b>{{ number_format($result['created']) }}</b><span>{{ __('سجل جديد') }}</span></div>
                    <div><b>{{ number_format($result['updated']) }}</b><span>{{ __('سجل محدَّث') }}</span></div>
                    <div><b>{{ number_format($result['skipped']) }}</b><span>{{ __('موجود مسبقًا') }}</span></div>
                    <div @class(['is-bad' => count($result['failed']) > 0])><b>{{ number_format(count($result['failed'])) }}</b><span>{{ __('لم يُستورد') }}</span></div>
                </div>
                @if ($result['failed'] !== [])
                    <table class="rowad-einv__table">
                        <thead><tr><th style="width: 90px">{{ __('الصف') }}</th><th>{{ __('السبب') }}</th></tr></thead>
                        <tbody>
                            @foreach (array_slice($result['failed'], 0, 50) as $p)
                                <tr><td>{{ $p['row'] }}</td><td>{{ implode(' · ', $p['problems']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
