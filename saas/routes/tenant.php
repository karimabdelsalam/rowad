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
    Route::get('/verify/{type}/{id}', [\App\Http\Controllers\PrintController::class, 'verify'])->middleware('signed')->whereNumber('id')->name('documents.verify');

    Route::get('/impersonate/{user}/{panel}', [\App\Http\Controllers\ImpersonationController::class, 'start'])
        ->middleware('signed')->name('portal.impersonate');
    Route::get('/impersonate/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])->name('portal.impersonate.stop');
});

Route::prefix('api/v1')->name('api.v1.')->middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\EnsureOfficeIsAccessible::class,
    \App\Http\Middleware\SetLocale::class,
])->group(base_path('routes/tenant-api.php'));
