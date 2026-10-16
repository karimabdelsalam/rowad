<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The office's portals as an installable phone app (PWA): one manifest per panel carrying the
 * office's own name, colour and mark, a service worker with an offline page, and the app icon.
 */
class AppController extends Controller
{
    public const PANELS = ['client', 'owner', 'admin'];

    public function manifest(string $panel): JsonResponse
    {
        abort_unless(in_array($panel, self::PANELS, true), 404);
        $name = Branding::name();
        $color = Branding::color() ?: '#0f766e';

        return response()->json([
            'id' => '/'.$panel,
            'name' => $name.' — '.Branding::panelTitle($panel),
            'short_name' => mb_strimwidth($name, 0, 24, '…'),
            'description' => Branding::panelTitle($panel),
            'lang' => app()->getLocale(),
            'dir' => app()->getLocale() === 'en' ? 'ltr' : 'rtl',
            'start_url' => '/'.$panel.'?source=pwa',
            'scope' => '/'.$panel,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f3f6f7',
            'theme_color' => $color,
            'icons' => [
                ['src' => '/app/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
                ['src' => '/app/icon.svg?maskable=1', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
                ['src' => asset('images/brand/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/brand/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The office monogram on its brand colour (SVG renders Arabic letters correctly, unlike GD). */
    public function icon(): Response
    {
        $color = Branding::color() ?: '#0f766e';
        $letters = e(Branding::initials());
        $maskable = request()->boolean('maskable');
        $radius = $maskable ? 0 : 112;
        $size = mb_strlen(Branding::initials()) > 1 ? 210 : 260;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$color}" stop-opacity=".82"/><stop offset="1" stop-color="{$color}"/></linearGradient></defs>
  <rect width="512" height="512" rx="{$radius}" fill="{$color}"/>
  <rect width="512" height="512" rx="{$radius}" fill="url(#g)"/>
  <text x="256" y="262" text-anchor="middle" dominant-baseline="central" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="{$size}" font-weight="800" fill="#fff">{$letters}</text>
</svg>
SVG;

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Network first for pages, so data is never stale; the offline page only when there is no network. */
    public function serviceWorker(): Response
    {
        $version = substr(md5(Branding::name().(Branding::color() ?? '').config('app.key')), 0, 8);
        $js = <<<JS
const CACHE = 'aqarnova-{$version}';
const OFFLINE = '/app/offline';
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/app/icon.svg'])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return; // Livewire updates, forms and payments always go to the network
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }
  const url = new URL(req.url);
  if (url.origin === location.origin && (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/images/'))) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache']);
    }

    public function offline(): View
    {
        return view('site.offline');
    }
}
