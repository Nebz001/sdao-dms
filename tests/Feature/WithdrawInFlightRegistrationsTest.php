<?php

use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Registrations\ApproveOrganizationRegistration;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

/*
 * documents.submitted_by is nullOnDelete — deleting a student with a pending
 * organization registration used to leave the Document behind in the SDAO
 * review queue with no submitter, and approving it would hit a NOT NULL
 * constraint on OrganizationMembership.user_id (the actual crash the test
 * plan's "san mapupunta pag naapprove na yon" describes). Covers all three
 * layers of the fix: withdrawal on delete, the queue filter, and the
 * approval-time guard.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
});

function submitPendingRegistration(User $student): Document
{
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    return app(SubmitOrganizationRegistration::class)->execute(
        name: 'Orphan Test Org',
        programId: null,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'A brand-new student organization.',
        contactPerson: 'Founding Student',
        contactNo: '09171234567',
        emailAddress: 'orphantestorg@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
        actor: $student,
        schoolId: test()->school->id,
        adviserId: $adviser->id,
    );
}

test('deleting a student withdraws their in-flight registration instead of leaving it orphaned', function () {
    $student = User::factory()->create();
    $document = submitPendingRegistration($student);

    expect($document->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($student)->delete(route('profile.destroy'), ['password' => 'password']);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Rejected);
    expect($document->current_step_position)->toBeNull();
    expect($document->submitted_by)->toBeNull();

    // Document::transitions() already orders by id ascending — ->last() on
    // the loaded collection is the most recent transition.
    $lastTransition = $document->transitions()->get()->last();
    expect($lastTransition->action)->toBe(TransitionAction::Withdrawn);
    expect($lastTransition->actor_id)->toBeNull();
    expect($lastTransition->to_status)->toBe(DocumentStatus::Rejected);
});

test('a withdrawn registration never appears in the SDAO review queue', function () {
    $student = User::factory()->create();
    $document = submitPendingRegistration($student);

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($student)->delete(route('profile.destroy'), ['password' => 'password']);

    $response = $this->actingAs($sdaoA)->get(route('review.registrations.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('queue', fn ($queue) => collect($queue)
        ->doesntContain(fn ($item) => $item['id'] === $document->id)
    ));
});

test('approving a registration with no submitter fails cleanly instead of crashing on a NOT NULL constraint', function () {
    $student = User::factory()->create();
    $document = submitPendingRegistration($student);

    // Simulate an orphan surviving some other path (e.g. pre-existing bad
    // data) rather than relying on WithdrawInFlightRegistrations itself,
    // since that action's whole job is to prevent this state from occurring.
    $document->forceFill(['submitted_by' => null])->save();

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => app(ApproveOrganizationRegistration::class)->execute($document->fresh(), $sdaoA))
        ->toThrow(ValidationException::class);

    // MembershipSeeder already seeds unrelated memberships — assert none was
    // created for THIS organization, rather than a global zero count.
    expect(OrganizationMembership::where('organization_id', $document->organization_id)->count())->toBe(0);
});
