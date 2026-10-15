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
});
