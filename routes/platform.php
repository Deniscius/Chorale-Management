<?php

use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('plateforme')->name('platform.')->group(function () {
    Route::get('/connexion', [SessionController::class, 'create'])->name('login');
    Route::post('/connexion', [SessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::middleware('platform.auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/chorales', [DashboardController::class, 'tenants'])->name('tenants');
        Route::get('/chorales/{tenant}', [DashboardController::class, 'show'])->whereNumber('tenant')->name('tenants.show');
        Route::post('/chorales/{tenant}', [DashboardController::class, 'update'])->whereNumber('tenant')->middleware('throttle:10,1')->name('tenants.update');
        Route::get('/supervision', [DashboardController::class, 'health'])->name('health');
        Route::get('/journal', [DashboardController::class, 'audit'])->name('audit');
        Route::post('/deconnexion', [SessionController::class, 'destroy'])->name('logout');
    });
});
