<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 * مسارات الكونسول.
 *
 * الترحيل تدريجي: تُضاف المسارات مع نقل كل صفحة، وما لم يُنقل بعد يظل يُخدَم من
 * النظام القديم. القائمة الجانبية تعرض الرابط فقط إن كان مساره مسجّلًا هنا.
 */

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'password'])->name('login.password');
    Route::post('/login/code', [LoginController::class, 'totp'])->name('login.totp');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
});
