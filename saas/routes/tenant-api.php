<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OfficeController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Middleware\EnsureUserType;
use Illuminate\Support\Facades\Route;

/*
| Mobile API on each office domain: https://<office-domain>/api/v1/...
| Bearer tokens from POST /auth/login (Sanctum), stored in the office's database.
*/

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('auth.login');
Route::post('auth/password/code', [AuthController::class, 'requestPasswordCode'])->middleware('throttle:10,1')->name('auth.password.code');
Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('auth.password.reset');

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AccountController::class, 'me'])->name('me');
    Route::post('devices', [AccountController::class, 'registerDevice'])->name('devices.store');
    Route::delete('devices', [AccountController::class, 'removeDevice'])->name('devices.destroy');
    Route::get('notifications', [AccountController::class, 'notifications'])->name('notifications');
    Route::post('notifications/read', [AccountController::class, 'markNotificationsRead'])->name('notifications.read');

    Route::middleware(EnsureUserType::class.':staff')->group(function () {
        Route::get('dashboard', [OfficeController::class, 'dashboard'])->name('dashboard');
        Route::get('units', [OfficeController::class, 'units'])->name('units.index');
        Route::get('units/{unit}', [OfficeController::class, 'unit'])->name('units.show');
        Route::get('contracts/{type}', [OfficeController::class, 'contracts'])->whereIn('type', ['rent', 'sale'])->name('contracts.index');
        Route::get('contracts/{type}/{id}', [OfficeController::class, 'contract'])->whereIn('type', ['rent', 'sale'])->whereNumber('id')->name('contracts.show');
        Route::get('installments', [OfficeController::class, 'installments'])->name('installments.index');
        Route::post('installments/{installment}/collect', [OfficeController::class, 'collect'])->name('installments.collect');
        Route::get('{type}', [OfficeController::class, 'parties'])->whereIn('type', ['owners', 'clients'])->name('parties.index');
        Route::get('{type}/{id}', [OfficeController::class, 'party'])->whereIn('type', ['owners', 'clients'])->whereNumber('id')->name('parties.show');
        Route::get('vouchers/{kind}', [OfficeController::class, 'vouchers'])->whereIn('kind', ['receipt', 'payment'])->name('vouchers.index');
    });

    Route::prefix('owner')->name('owner.')->middleware(EnsureUserType::class.':owner')->group(function () {
        Route::get('summary', [PortalController::class, 'ownerSummary'])->name('summary');
        Route::get('units', [PortalController::class, 'ownerUnits'])->name('units');
        Route::get('statement', [PortalController::class, 'ownerLedger'])->name('statement');
        Route::get('tickets', [PortalController::class, 'tickets'])->name('tickets.index');
        Route::post('tickets', [PortalController::class, 'createTicket'])->name('tickets.store');
    });

    Route::prefix('client')->name('client.')->middleware(EnsureUserType::class.':client')->group(function () {
        Route::get('summary', [PortalController::class, 'clientSummary'])->name('summary');
        Route::get('contracts', [PortalController::class, 'clientContracts'])->name('contracts');
        Route::get('installments', [PortalController::class, 'clientInstallments'])->name('installments');
        Route::get('tickets', [PortalController::class, 'tickets'])->name('tickets.index');
        Route::post('tickets', [PortalController::class, 'createTicket'])->name('tickets.store');
    });
});
