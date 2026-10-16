<?php

declare(strict_types=1);

use App\Http\Controllers\Site\LandingController;
use App\Http\Controllers\Site\MediaController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
| Office domains (subdomain or custom domain). The back-office and portals
| are Filament panels (/admin, /owner, /client); these are the public pages.
*/

// A cancelled office is closed, so coming back cannot go through its panels: a signed link from the
// cancellation email (valid until the purge date) reopens it, and the closed page can resend that link.
Route::middleware(['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])->group(function () {
    Route::get('/reactivate', [\App\Http\Controllers\Site\ReactivationController::class, 'show'])->middleware('signed:relative')->name('office.reactivate');
    Route::post('/reactivate', [\App\Http\Controllers\Site\ReactivationController::class, 'store'])->middleware('signed:relative');
    Route::post('/reactivate/resend', [\App\Http\Controllers\Site\ReactivationController::class, 'resend'])->middleware('throttle:3,10')->name('office.reactivate.resend');
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\EnsureOfficeIsAccessible::class,
])->group(function () {
    Route::get('/', [LandingController::class, 'home'])->name('site.home');
    Route::get('/units/{unit}', [LandingController::class, 'show'])->name('site.unit');
    Route::post('/contact', [LandingController::class, 'inquire'])->name('site.contact');
    Route::post('/units/{unit}/inquiry', [LandingController::class, 'inquire'])->name('site.unit.inquiry');
    Route::get('/media/{media}', MediaController::class)->name('site.media');

    // the portals as an installable phone app (PWA)
    Route::get('/manifest/{panel}.webmanifest', [\App\Http\Controllers\Site\AppController::class, 'manifest'])->whereIn('panel', \App\Http\Controllers\Site\AppController::PANELS)->name('app.manifest');
    Route::get('/app/icon.svg', [\App\Http\Controllers\Site\AppController::class, 'icon'])->name('app.icon');
    Route::get('/sw.js', [\App\Http\Controllers\Site\AppController::class, 'serviceWorker'])->name('app.sw');
    Route::get('/app/offline', [\App\Http\Controllers\Site\AppController::class, 'offline'])->name('app.offline');

    // e-collection: the public pay page of an installment (token from a reminder or the client portal)
    Route::get('/pay/{token}', [\App\Http\Controllers\Site\PayController::class, 'show'])->middleware('throttle:60,1')->name('pay.show');
    Route::get('/pay/{token}/callback', [\App\Http\Controllers\Site\PayController::class, 'callback'])->middleware('throttle:30,1')->name('pay.callback');
    Route::post('/webhooks/moyasar', [\App\Http\Controllers\Site\PayController::class, 'webhook'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])->middleware('throttle:120,1')->name('pay.webhook');
    Route::get('/branding/logo', function () {
        $path = \App\Support\Settings::get('logo');
        abort_unless(filled($path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('public')->response($path, headers: [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox", // an SVG logo opened directly cannot run scripts
        ]);
    })->name('site.logo');

    Route::get('/print/{type}/{id}', \App\Http\Controllers\PrintController::class)->middleware('auth')->whereNumber('id')->name('documents.print');
    Route::get('/print/qr/{kind}', [\App\Http\Controllers\PrintController::class, 'qr'])->middleware('auth')->whereIn('kind', ['owner', 'client', 'site'])->name('documents.qr');
    Route::get('/verify/{type}/{id}', [\App\Http\Controllers\PrintController::class, 'verify'])->middleware('signed')->whereNumber('id')->name('documents.verify');

    Route::get('/impersonate/{user}/{panel}', [\App\Http\Controllers\ImpersonationController::class, 'start'])
        ->middleware('signed')->name('portal.impersonate');
    Route::get('/demo/{role}', \App\Http\Controllers\DemoLoginController::class)->whereIn('role', ['admin', 'owner', 'client'])
        ->middleware('throttle:30,1')->name('demo.login');

    Route::get('/impersonate/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])->name('portal.impersonate.stop');
    // platform console → "open as the office admin" (signed link generated for this office's domain)
    Route::get('/platform-access/{user}', \App\Http\Controllers\PlatformAccessController::class)
        ->middleware(['signed', 'throttle:10,1'])->name('platform.access');
});

Route::prefix('api/v1')->name('api.v1.')->middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\EnsureOfficeIsAccessible::class,
    \App\Http\Middleware\SetLocale::class,
    \App\Http\Middleware\EnsurePlanFeature::class.':api',
])->group(base_path('routes/tenant-api.php'));
