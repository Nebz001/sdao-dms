<?php

use App\Http\Controllers\ActivityCalendarController;
use App\Http\Controllers\ActivityCalendarReviewController;
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\ActivityProposalReviewController;
use App\Http\Controllers\Admin\ApproverController;
use App\Http\Controllers\Admin\CurrentTermController;
use App\Http\Controllers\Admin\PendingAccountController;
use App\Http\Controllers\AfterActivityReportController;
use App\Http\Controllers\AfterActivityReportReviewController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\OrganizationOfficerController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\RenewalReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Attachments (Phase 2 item 8) — generic across every form type.
    // store/destroy are Mode B (attach-to-existing-document, e.g. Activity
    // Proposal's optional resume slot); download is shared by Mode A too.
    Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    // Adviser — officer binding
    Route::get('/organizations/{organization}/officers', [OrganizationOfficerController::class, 'index'])->name('officers.index');
    Route::post('/organizations/{organization}/officers', [OrganizationOfficerController::class, 'store'])->name('officers.store');
    Route::delete('/organizations/{organization}/officers/{membership}', [OrganizationOfficerController::class, 'destroy'])->name('officers.destroy');

    // Student — registration lifecycle (literal paths declared before {document} wildcard)
    Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/create', [RegistrationController::class, 'create'])->name('registrations.create');
    Route::get('/registrations/adviser-search', [RegistrationController::class, 'adviserSearch'])->name('registrations.adviser-search');
    Route::post('/registrations', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registrations/{document}', [RegistrationController::class, 'show'])->name('registrations.show');
    Route::get('/registrations/{document}/edit', [RegistrationController::class, 'edit'])->name('registrations.edit');
    Route::put('/registrations/{document}', [RegistrationController::class, 'update'])->name('registrations.update');

    // SDAO — registration review queue
    Route::get('/review/registrations', [RegistrationReviewController::class, 'index'])->name('review.registrations.index');
    Route::get('/review/registrations/{document}', [RegistrationReviewController::class, 'show'])->name('review.registrations.show');
    Route::post('/review/registrations/{document}/approve', [RegistrationReviewController::class, 'approve'])->name('review.registrations.approve');
    Route::post('/review/registrations/{document}/reject', [RegistrationReviewController::class, 'reject'])->name('review.registrations.reject');
    Route::post('/review/registrations/{document}/return', [RegistrationReviewController::class, 'return'])->name('review.registrations.return');

    // Shared venue calendar
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Student — activity calendar lifecycle
    Route::get('/activity-calendars', [ActivityCalendarController::class, 'index'])->name('activity-calendars.index');
    Route::post('/activity-calendars/conflict-check', [ActivityCalendarController::class, 'conflictCheck'])->name('activity-calendars.conflict-check');
    Route::get('/activity-calendars/create', [ActivityCalendarController::class, 'create'])->name('activity-calendars.create');
    Route::post('/activity-calendars', [ActivityCalendarController::class, 'store'])->name('activity-calendars.store');
    Route::get('/activity-calendars/{document}', [ActivityCalendarController::class, 'show'])->name('activity-calendars.show');
    Route::get('/activity-calendars/{document}/edit', [ActivityCalendarController::class, 'edit'])->name('activity-calendars.edit');
    Route::put('/activity-calendars/{document}', [ActivityCalendarController::class, 'update'])->name('activity-calendars.update');

    // SDAO — activity calendar review queue
    Route::get('/review/activity-calendars', [ActivityCalendarReviewController::class, 'index'])->name('review.activity-calendars.index');
    Route::get('/review/activity-calendars/{document}', [ActivityCalendarReviewController::class, 'show'])->name('review.activity-calendars.show');
    Route::post('/review/activity-calendars/{document}/approve', [ActivityCalendarReviewController::class, 'approve'])->name('review.activity-calendars.approve');
    Route::post('/review/activity-calendars/{document}/reject', [ActivityCalendarReviewController::class, 'reject'])->name('review.activity-calendars.reject');
    Route::post('/review/activity-calendars/{document}/return', [ActivityCalendarReviewController::class, 'return'])->name('review.activity-calendars.return');

    // Student — activity proposal lifecycle (literal paths declared before {document} wildcard)
    Route::get('/activity-proposals', [ActivityProposalController::class, 'index'])->name('activity-proposals.index');
    Route::get('/activity-proposals/create', [ActivityProposalController::class, 'create'])->name('activity-proposals.create');
    Route::post('/activity-proposals', [ActivityProposalController::class, 'store'])->name('activity-proposals.store');
    Route::post('/activity-proposals/conflict-check', [ActivityProposalController::class, 'conflictCheck'])->name('activity-proposals.conflict-check');
    Route::get('/activity-proposals/on-calendar-activities', [ActivityProposalController::class, 'onCalendarActivities'])->name('activity-proposals.on-calendar-activities');
    Route::get('/activity-proposals/{document}', [ActivityProposalController::class, 'show'])->name('activity-proposals.show');
    Route::get('/activity-proposals/{document}/edit', [ActivityProposalController::class, 'edit'])->name('activity-proposals.edit');
    Route::get('/activity-proposals/{document}/continue', [ActivityProposalController::class, 'continue'])->name('activity-proposals.continue');
    Route::patch('/activity-proposals/{document}/draft', [ActivityProposalController::class, 'draft'])->name('activity-proposals.draft');
    Route::post('/activity-proposals/{document}/submit', [ActivityProposalController::class, 'submit'])->name('activity-proposals.submit');
    Route::put('/activity-proposals/{document}', [ActivityProposalController::class, 'update'])->name('activity-proposals.update');

    // Approvers — activity proposal review queue
    Route::get('/review/activity-proposals', [ActivityProposalReviewController::class, 'index'])->name('review.activity-proposals.index');
    Route::get('/review/activity-proposals/{document}', [ActivityProposalReviewController::class, 'show'])->name('review.activity-proposals.show');
    Route::post('/review/activity-proposals/{document}/approve', [ActivityProposalReviewController::class, 'approve'])->name('review.activity-proposals.approve');
    Route::post('/review/activity-proposals/{document}/reject', [ActivityProposalReviewController::class, 'reject'])->name('review.activity-proposals.reject');
    Route::post('/review/activity-proposals/{document}/return', [ActivityProposalReviewController::class, 'return'])->name('review.activity-proposals.return');

    // Student — organization renewal lifecycle
    Route::get('/renewals', [RenewalController::class, 'index'])->name('renewals.index');
    Route::get('/renewals/create', [RenewalController::class, 'create'])->name('renewals.create');
    Route::post('/renewals', [RenewalController::class, 'store'])->name('renewals.store');
    Route::get('/renewals/{document}', [RenewalController::class, 'show'])->name('renewals.show');
    Route::get('/renewals/{document}/edit', [RenewalController::class, 'edit'])->name('renewals.edit');
    Route::put('/renewals/{document}', [RenewalController::class, 'update'])->name('renewals.update');

    // SDAO — renewal review queue
    Route::get('/review/renewals', [RenewalReviewController::class, 'index'])->name('review.renewals.index');
    Route::get('/review/renewals/{document}', [RenewalReviewController::class, 'show'])->name('review.renewals.show');
    Route::post('/review/renewals/{document}/approve', [RenewalReviewController::class, 'approve'])->name('review.renewals.approve');
    Route::post('/review/renewals/{document}/reject', [RenewalReviewController::class, 'reject'])->name('review.renewals.reject');
    Route::post('/review/renewals/{document}/return', [RenewalReviewController::class, 'return'])->name('review.renewals.return');

    // Student — after-activity report lifecycle
    Route::get('/reports', [AfterActivityReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [AfterActivityReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [AfterActivityReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{document}', [AfterActivityReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{document}/edit', [AfterActivityReportController::class, 'edit'])->name('reports.edit');
    Route::put('/reports/{document}', [AfterActivityReportController::class, 'update'])->name('reports.update');

    // SDAO — after-activity report review queue
    Route::get('/review/reports', [AfterActivityReportReviewController::class, 'index'])->name('review.reports.index');
    Route::get('/review/reports/{document}', [AfterActivityReportReviewController::class, 'show'])->name('review.reports.show');
    Route::post('/review/reports/{document}/approve', [AfterActivityReportReviewController::class, 'approve'])->name('review.reports.approve');
    Route::post('/review/reports/{document}/reject', [AfterActivityReportReviewController::class, 'reject'])->name('review.reports.reject');
    Route::post('/review/reports/{document}/return', [AfterActivityReportReviewController::class, 'return'])->name('review.reports.return');

    // SDAO admin — approver provisioning + account verification
    Route::middleware('can:access-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/approvers', [ApproverController::class, 'index'])->name('approvers.index');
        Route::get('/approvers/create', [ApproverController::class, 'create'])->name('approvers.create');
        Route::post('/approvers', [ApproverController::class, 'store'])->name('approvers.store');

        Route::get('/pending-accounts', [PendingAccountController::class, 'index'])->name('pending-accounts.index');
        Route::post('/pending-accounts/{account}/verify', [PendingAccountController::class, 'verify'])->name('pending-accounts.verify');
        Route::post('/pending-accounts/{account}/reject', [PendingAccountController::class, 'reject'])->name('pending-accounts.reject');

        // SDAO admin — global settings (Phase 2 item 6: current term)
        Route::get('/settings/term', [CurrentTermController::class, 'edit'])->name('settings.term.edit');
        Route::put('/settings/term', [CurrentTermController::class, 'update'])->name('settings.term.update');
    });
});

require __DIR__.'/settings.php';
