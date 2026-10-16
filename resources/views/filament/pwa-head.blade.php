@php($panel = filament()->getCurrentPanel()?->getId())
@if (in_array($panel, \App\Http\Controllers\Site\AppController::PANELS, true))
    <link rel="manifest" href="/manifest/{{ $panel }}.webmanifest">
    <meta name="theme-color" content="{{ \App\Support\Branding::color() ?: '#0b2a2e' }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ mb_strimwidth(\App\Support\Branding::name(), 0, 24, '…') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
        }
    </script>
@endif
