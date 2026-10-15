<x-filament-widgets::widget>
    <div class="rowad-welcome">
        <div>
            <p class="rowad-welcome__date">{{ $gregorian }} <span>·</span> {{ $hijri }}</p>
            <h2 class="rowad-welcome__title">{{ $greeting }}{{ app()->getLocale() === 'ar' ? '،' : ',' }} {{ $name }}</h2>
            <p class="rowad-welcome__sub">{{ $subtitle }}</p>
        </div>
        @if ($actions)
            <div class="rowad-welcome__actions">
                @foreach ($actions as $action)
                    <a href="{{ $action['url'] }}" class="rowad-welcome__action" @if (! empty($action['external'])) target="_blank" rel="noopener" @endif>
                        <x-filament::icon :icon="$action['icon']" class="rowad-welcome__icon" />
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
