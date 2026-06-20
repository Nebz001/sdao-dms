<?php

use App\Http\Controllers\Dev\DevLoginController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (! app()->isProduction()) {
    Route::get('/dev/login', [DevLoginController::class, 'index'])->name('dev.login');
    Route::post('/dev/login', [DevLoginController::class, 'store'])->name('dev.login.store');
    Route::post('/dev/logout', [DevLoginController::class, 'destroy'])->name('dev.logout');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
