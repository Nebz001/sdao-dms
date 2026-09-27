<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\DocumentActionController;
use App\Http\Controllers\Api\Mobile\DocumentController;
use Illuminate\Support\Facades\Route;

// Mobile approver API — the SDAO DMS React Native app. Approver-only,
// Activity Proposals only. Bearer tokens (Sanctum), never a session.
Route::post('/mobile/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.mobile.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mobile/logout', [AuthController::class, 'logout'])
        ->name('api.mobile.logout');

    Route::middleware(['mobile.access', 'throttle:mobile-api'])->group(function () {
        Route::get('/mobile/user', [AuthController::class, 'user'])
            ->name('api.mobile.user');

        // Literal path before the {proposalReference} wildcard below.
        Route::get('/documents/queue', [DocumentController::class, 'queue'])
            ->name('api.mobile.documents.queue');

        Route::get('/documents/{proposalReference}', [DocumentController::class, 'show'])
            ->name('api.mobile.documents.show');

        Route::get('/documents/{proposalReference}/attachments/{attachmentId}/download', [DocumentController::class, 'downloadAttachment'])
            ->name('api.mobile.documents.attachments.download');
    });

    Route::middleware(['mobile.access', 'throttle:mobile-review'])->group(function () {
        Route::post('/documents/{proposalReference}/approve', [DocumentActionController::class, 'approve'])
            ->name('api.mobile.documents.approve');

        Route::post('/documents/{proposalReference}/request-revision', [DocumentActionController::class, 'requestRevision'])
            ->name('api.mobile.documents.request-revision');

        Route::post('/documents/{proposalReference}/reject', [DocumentActionController::class, 'reject'])
            ->name('api.mobile.documents.reject');
    });
});
