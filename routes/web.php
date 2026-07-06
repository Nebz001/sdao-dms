<?php

use App\Http\Controllers\Dev\DevLoginController;
use App\Http\Controllers\OrganizationOfficerController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (! app()->isProduction()) {
    Route::get('/dev/login', [DevLoginController::class, 'index'])->name('dev.login');
    Route::post('/dev/login', [DevLoginController::class, 'store'])->name('dev.login.store');
    Route::post('/dev/logout', [DevLoginController::class, 'destroy'])->name('dev.logout');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Adviser — officer binding
    Route::get('/organizations/{organization}/officers', [OrganizationOfficerController::class, 'index'])->name('officers.index');
    Route::post('/organizations/{organization}/officers', [OrganizationOfficerController::class, 'store'])->name('officers.store');
    Route::delete('/organizations/{organization}/officers/{membership}', [OrganizationOfficerController::class, 'destroy'])->name('officers.destroy');

    // Student — registration lifecycle
    Route::get('/registrations/create', [RegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/registrations', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registrations/{document}', [RegistrationController::class, 'show'])->name('registrations.show');
    Route::get('/registrations/{document}/edit', [RegistrationController::class, 'edit'])->name('registrations.edit');
    Route::put('/registrations/{document}', [RegistrationController::class, 'update'])->name('registrations.update');

    // SDAO — review queue
    Route::get('/review/registrations', [RegistrationReviewController::class, 'index'])->name('review.registrations.index');
    Route::get('/review/registrations/{document}', [RegistrationReviewController::class, 'show'])->name('review.registrations.show');
    Route::post('/review/registrations/{document}/approve', [RegistrationReviewController::class, 'approve'])->name('review.registrations.approve');
    Route::post('/review/registrations/{document}/reject', [RegistrationReviewController::class, 'reject'])->name('review.registrations.reject');
    Route::post('/review/registrations/{document}/return', [RegistrationReviewController::class, 'return'])->name('review.registrations.return');
});

require __DIR__.'/settings.php';
