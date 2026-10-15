<?php

use App\Http\Controllers\LocaleController;
use App\Http\Middleware\InitializeTenancyIfNotCentral;
use Illuminate\Support\Facades\Route;

// Works on the platform domains and on every office domain.
Route::middleware(InitializeTenancyIfNotCentral::class)
    ->get('/locale/{locale}', LocaleController::class)
    ->name('locale.switch');

// Platform (central) pages only; office domains are served by routes/tenant.php.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', fn () => view('welcome'))->name('central.home');
    });
}
