@if ($notice)
    <div class="rowad-plan-notice rowad-plan-notice--{{ $notice['level'] }}" role="status">
        <x-filament::icon :icon="$notice['level'] === 'danger' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock'" class="rowad-plan-notice__icon" />
        <span>{{ $notice['text'] }}</span>
        @unless (request()->routeIs('filament.admin.pages.subscription'))
            <a href="{{ $notice['url'] }}">{{ __('صفحة الاشتراك') }}</a>
        @endunless
    </div>
@endif
