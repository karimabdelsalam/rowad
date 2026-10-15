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
// Only the first domain carries the canonical names (route:cache needs unique names); the
// others get a prefix. Same paths everywhere, and the pages link with relative URLs.
foreach (array_values(config('tenancy.central_domains')) as $i => $domain) {
    Route::domain($domain)->name($i === 0 ? '' : "domain{$i}.")->group(function () {
        Route::get('/', [SignupController::class, 'home'])->name('central.home');
        Route::get('/signup', [SignupController::class, 'create'])->name('central.signup');
        Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:5,10')->name('central.signup.store');
        Route::get('/signup/done', [SignupController::class, 'done'])->name('central.signup.done');
    });
}
