@php
    // what a tenant or owner opens most, in that order; the assistant already has its own topbar button
    $priority = match (filament()->getCurrentPanel()?->getId()) {
        'client' => ['installments', 'rent-contracts', 'support-tickets', 'units'],
        'owner' => ['units', 'owner-transactions', 'installments', 'rent-contracts'],
        default => [],
    };
    $all = collect(filament()->getNavigation())->flatMap(fn ($group) => $group->getItems())
        ->filter(fn ($item) => $item->isVisible() && ! str_contains((string) $item->getUrl(), '/assistant'))->values();
    $rank = function ($item) use ($priority) {
        $path = trim((string) parse_url((string) $item->getUrl(), PHP_URL_PATH), '/');
        $segment = \Illuminate\Support\Str::after($path, '/');
        $at = array_search($segment, $priority, true);

        return $path === \Illuminate\Support\Str::before($path.'/', '/') ? -1 : ($at === false ? 100 : $at); // the dashboard first
    };
    $items = $all->sortBy($rank)->take(4)->values();
@endphp
{{-- portals on a phone: a bottom tab bar like a native app, and a one-time "install the app" hint --}}
<nav class="rowad-tabbar" aria-label="{{ __('التنقل السريع') }}">
    @foreach ($items as $item)
        <a href="{{ $item->getUrl() }}" @class(['rowad-tabbar__item', 'is-active' => $item->isActive()])>
            <x-filament::icon :icon="$item->isActive() ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()" class="rowad-tabbar__icon" />
            <span>{{ $item->getLabel() }}</span>
        </a>
    @endforeach
    <button type="button" class="rowad-tabbar__item" x-data x-on:click="$store.sidebar.open()">
        <svg class="rowad-tabbar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
        <span>{{ __('المزيد') }}</span>
    </button>
</nav>

<div class="rowad-install" x-data="{
        show: false, ios: false, deferred: null,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            let dismissed = false; try { dismissed = localStorage.getItem('rowad-install-dismissed') === '1'; } catch (e) {}
            if (standalone || dismissed || window.innerWidth > 1024) return;
            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (this.ios) { this.show = true; return; }
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.deferred = e; this.show = true; });
        },
        async install() { if (! this.deferred) return; this.deferred.prompt(); await this.deferred.userChoice; this.close(); },
        close() { this.show = false; try { localStorage.setItem('rowad-install-dismissed', '1'); } catch (e) {} },
    }" x-show="show" x-cloak x-transition>
    <img src="/app/icon.svg" alt="" width="40" height="40">
    <div class="rowad-install__text">
        <b>{{ __('ثبّت التطبيق على جوالك') }}</b>
        <small x-show="! ios">{{ __('افتح البوابة بلمسة من الشاشة الرئيسية، بلا متجر تطبيقات.') }}</small>
        <small x-show="ios">{{ __('اضغط زر المشاركة ثم "إضافة إلى الشاشة الرئيسية".') }}</small>
    </div>
    <button type="button" class="rowad-install__go" x-show="! ios" x-on:click="install()">{{ __('تثبيت') }}</button>
    <button type="button" class="rowad-install__close" x-on:click="close()" aria-label="{{ __('إغلاق') }}">×</button>
</div>
