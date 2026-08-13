<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // A school-email change is gated behind a verification code sent to the
    // new address — see ProfileController::update()/verifyEmail*().
    Route::get('settings/profile/verify-email', [ProfileController::class, 'verifyEmail'])
        ->name('profile.verify-email');
    Route::post('settings/profile/verify-email', [ProfileController::class, 'verifyEmailStore'])
        ->middleware('throttle:10,1')
        ->name('profile.verify-email.store');
    Route::post('settings/profile/verify-email/resend', [ProfileController::class, 'verifyEmailResend'])
        ->middleware('throttle:email-code-resend')
        ->name('profile.verify-email.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
