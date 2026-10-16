{{-- the office's monogram when the sidebar is collapsed to icons (a wide logo would not fit) --}}
<a href="{{ filament()->getHomeUrl() }}" class="rowad-side-mark" x-show="! $store.sidebar.isOpen" x-cloak title="{{ \App\Support\Branding::name() }}">
    <span class="rowad-brand__mark">{{ \App\Support\Branding::initials() }}</span>
</a>
