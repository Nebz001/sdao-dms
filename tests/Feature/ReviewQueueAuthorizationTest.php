<?php

use App\Approval\ApprovalEngine;
use App\Enums\FormType;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Security gap fix: RegistrationReviewController::index(),
 * ActivityCalendarReviewController::index(), RenewalReviewController::index(),
 * and AfterActivityReportReviewController::index() previously ran an
 * unfiltered `Document::where('status', InReview)->get()` with no
 * authorization check at all — any authenticated, email-verified account
 * (a plain student, an adviser or dean with no stake in the document, or a
 * self-registered account SDAO never approved) could enumerate every
 * organization's in-review documents for these four short-chain form types.
 *
 * The fix filters each queue through Gate::allows('review', $document) —
 * DocumentPolicy::review(), the same "actor is the current-step approver"
 * predicate ActivityProposalReviewController::index() already uses, derived
 * from workflow configuration rather than a hardcoded SDAO check (invariant
 * #1). These tests pin: an unrelated account sees an empty queue, while the
 * legitimate SDAO approver still sees it — reusing shortChainInReviewDoc()
 * from SectionFlagValidationTest.php (Pest loads all Feature files up front).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

test('a legitimate SDAO approver sees the queue, but a student, a bare account, and an approver from an unrelated chain see nothing', function (FormType $formType, string $routeName) {
    shortChainInReviewDoc($formType, $this->org, $this->engine, $this->studentAlpha);

    // The legitimate current-step approver sees it.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 1));

    // An org officer with no approver role at all.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 0));

    // A bare, role-less account — the exact scenario the original gap exposed.
    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)
        ->withoutVite()
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 0));

    // A real approver, but only in the LONG proposal chains — never a
    // current-step approver for a short (single-SDAO-step) chain. Proves the
    // filter is scope-correct, not merely "logged in vs not."
    $this->actingAs($this->adviserOne)
        ->withoutVite()
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 0));

    // Same reasoning, a different scope type (school-scoped Dean).
    $this->actingAs($this->deanCcit)
        ->withoutVite()
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 0));
})->with([
    'registration' => [FormType::OrganizationRegistration, 'review.registrations.index'],
    'renewal' => [FormType::OrganizationRenewal, 'review.renewals.index'],
    'activity calendar' => [FormType::ActivityCalendar, 'review.activity-calendars.index'],
    'after-activity report' => [FormType::AfterActivityReport, 'review.reports.index'],
]);

test('SDAO global scope is preserved: an SDAO member sees in-review documents from every organization, not just one', function () {
    shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    shortChainInReviewDoc(FormType::OrganizationRegistration, $this->itGuild, $this->engine, $studentBeta);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 2)
            ->where('queue.0.organization.name', $this->org->name)
            ->where('queue.1.organization.name', $this->itGuild->name)
        );
});
