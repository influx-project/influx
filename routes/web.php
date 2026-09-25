<?php

use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('services', ServiceController::class);
    Route::get('services/{service}/downtime', [ServiceController::class, 'downtime'])->name('services.downtime');
    Route::get('services/{service}/alerts', [ServiceController::class, 'alerts'])->name('services.alerts');
    Route::get('services/{service}/information', [ServiceController::class, 'information'])->name('services.information');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
