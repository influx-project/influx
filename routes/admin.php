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
    });
