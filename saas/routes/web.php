<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Platform\SignupController;
use App\Http\Middleware\InitializeTenancyIfNotCentral;
use Illuminate\Support\Facades\Route;

// Works on the platform domains and on every office domain.
Route::middleware(InitializeTenancyIfNotCentral::class)
    ->get('/locale/{locale}', LocaleController::class)
    ->name('locale.switch');

// Platform (central) pages only; office domains are served by routes/tenant.php.
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', [SignupController::class, 'home'])->name('central.home');
        Route::get('/signup', [SignupController::class, 'create'])->name('central.signup');
        Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:5,10')->name('central.signup.store');
        Route::get('/signup/done', [SignupController::class, 'done'])->name('central.signup.done');
    });
}
