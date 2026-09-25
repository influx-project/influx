<?php

use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', OverviewController::class)->name('overview');
        Route::resource('users', UserController::class);
        Route::resource('services', ServiceController::class);
        Route::get('services/{service}/downtime', [ServiceController::class, 'downtime'])->name('services.downtime');
        Route::get('services/{service}/alerts', [ServiceController::class, 'alerts'])->name('services.alerts');
        Route::get('services/{service}/information', [ServiceController::class, 'information'])->name('services.information');
    });
