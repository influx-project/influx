<?php

use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('users', UserController::class);
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::apiResource('services', ServiceController::class);
});
