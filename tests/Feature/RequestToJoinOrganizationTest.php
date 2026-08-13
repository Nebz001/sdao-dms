<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\JoinRequestStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestReceivedNotification;
use App\Organizations\RequestToJoinOrganization;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(RequestToJoinOrganization::class);
    // IT Guild has a President (Student Beta) but no Secretary — useful for
    // both this file's happy path and ReviewJoinRequestsTest's approve tests.
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
});

test('a verified unaffiliated student can request to join an organization', function () {
    Notification::fake();
    $student = User::factory()->create();

    $joinRequest = $this->action->execute($student, $this->itGuild);

    expect($joinRequest->status)->toBe(JoinRequestStatus::Pending);
    expect($joinRequest->user_id)->toBe($student->id);
    expect($joinRequest->organization_id)->toBe($this->itGuild->id);
});

test('filing a request notifies the adviser and active officers', function () {
    Notification::fake();
    $student = User::factory()->create();

    $this->action->execute($student, $this->itGuild);

    $adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();

    Notification::assertSentTo($adviserTwo, JoinRequestReceivedNotification::class);
    Notification::assertSentTo($studentBeta, JoinRequestReceivedNotification::class);
});

test('an unverified account may still file a join request — verification is checked at approval, not here', function () {
    Notification::fake();
    $student = User::factory()->unverifiedAccount()->create();

    $joinRequest = $this->action->execute($student, $this->itGuild);

    expect($joinRequest->status)->toBe(JoinRequestStatus::Pending);
});

test('a student already an active officer elsewhere cannot request to join another org', function () {
    $studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => $this->action->execute($studentAlpha, $this->itGuild))
        ->toThrow(ValidationException::class);
});

test('a student with an existing pending request cannot file a second one', function () {
    Notification::fake();
    $student = User::factory()->create();
    $this->action->execute($student, $this->itGuild);

    $computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();

    expect(fn () => $this->action->execute($student, $computingSociety))
        ->toThrow(ValidationException::class);
});

test('a student with an in-flight registration cannot also file a join request', function () {
    $student = User::factory()->create();
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'status' => DocumentStatus::InReview,
        'submitted_by' => $student->id,
    ]);

    expect(fn () => $this->action->execute($student, $this->itGuild))
        ->toThrow(ValidationException::class);
});
