<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // president, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // secretary, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(); // president, IT Guild
});

/**
 * Builds and submits a registration document directly for an org the actor
 * is ALREADY bound to (not via SubmitOrganizationRegistration, which now
 * requires a not-yet-affiliated founding student — Phase 2 item 5). This
 * test is about index() visibility by org membership, not submission
 * mechanics; the founding student's own-pending-proposal visibility is
 * covered separately below via submitFoundingRegistration().
 */
function submitRegistrationFor(User $actor, Organization $org): Document
{
    $document = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $actor->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $document->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'A student organization.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09171234567',
        'email_address' => 'contact@example.test',
        'date_organized' => '2024-06-01',
        'adviser_id' => null,
    ]);
    app(ApprovalEngine::class)->submit($document, $actor);

    return $document->refresh();
}

/**
 * The other half of index()'s audience: a not-yet-affiliated founding
 * student proposing a brand-new org, via the real action (Phase 2 item 5) —
 * no pre-existing membership, adviser provisioned fresh per call unless one
 * is passed in.
 */
function submitFoundingRegistration(User $actor, string $name, ?User $adviser = null): Document
{
    $adviser ??= tap(User::factory()->create(), fn (User $a) => RoleAssignment::create([
        'user_id' => $a->id,
        'role' => Role::Adviser->value,
    ]));

    return app(SubmitOrganizationRegistration::class)->execute(
        actor: $actor,
        name: $name,
        schoolId: School::where('name', 'School of Computing and IT')->firstOrFail()->id,
        programId: null,
        adviserId: $adviser->id,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Description.',
        contactPerson: 'Contact Person',
        contactNo: '09170000000',
        emailAddress: 'contact@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );
}

test('officer sees their org registration in the index', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations.data', 1)
            ->where('registrations.data.0.organization.name', 'Computing Society')
        );
});

test('both president and secretary of the same org see the same registration', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentDelta)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations.data', 1)
        );
});

test('an org officer does not see another org\'s registration', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentBeta)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations.data', 0)
        );
});

test('a founding student with no org sees their own submitted registration and only their own', function () {
    $founder = User::factory()->create();
    $otherFounder = User::factory()->create();

    $document = submitFoundingRegistration($founder, 'Founder Org');
    submitFoundingRegistration($otherFounder, 'Other Founder Org');

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations.data', 1)
            ->where('registrations.data.0.id', $document->id)
            ->where('registrations.data.0.organization.name', 'Founder Org')
        );
});

test('an unaffiliated student with nothing submitted yet gets an empty list and a 200, not a 403', function () {
    $bareStudent = User::factory()->create();

    $this->actingAs($bareStudent)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations.data', 0)
            ->where('stats.total', 0)
        );
});

test('a status filter narrows the results', function () {
    $founder = User::factory()->create();
    $document = submitFoundingRegistration($founder, 'Founder Org');
    // A second, terminal document for the same founder — rejecting frees a
    // founding student to try again (Phase 2 item 4), so both can coexist.
    Document::factory()->create(['submitted_by' => $founder->id, 'status' => DocumentStatus::Rejected]);

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index', ['status' => 'in_review']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 1)
            ->where('registrations.data.0.id', $document->id)
        );
});

test('an unrecognized status filter value is ignored rather than emptying the page', function () {
    $founder = User::factory()->create();
    submitFoundingRegistration($founder, 'Founder Org');

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index', ['status' => 'not-a-real-status']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 1)
            ->where('filters.status', null)
        );
});

test('search matches the document title, which already embeds the organization name', function () {
    $founder = User::factory()->create();
    submitFoundingRegistration($founder, 'Chess Club');
    // A second document for the same viewer, added directly (a real second
    // submission would be blocked by the one-in-flight-registration rule,
    // Phase 2 item 4) — this test is about the search filter, not
    // submission mechanics.
    Document::factory()->create(['submitted_by' => $founder->id, 'title' => 'Debate Society']);

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index', ['search' => 'Chess Club']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 1)
            ->where('registrations.data.0.organization.name', 'Chess Club')
        );
});

test('results are paginated at 20 per page, newest first', function () {
    $founder = User::factory()->create();

    // Force distinct, increasing timestamps so ordering is deterministic
    // rather than relying on same-second factory timestamps.
    foreach (range(1, 25) as $i) {
        Document::factory()->create([
            'title' => "Doc {$i}",
            'submitted_by' => $founder->id,
            'status' => DocumentStatus::InReview,
            'updated_at' => now()->addSeconds($i),
            'created_at' => now()->addSeconds($i),
        ]);
    }

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 20)
            ->where('registrations.meta.last_page', 2)
            ->where('registrations.meta.total', 25)
            ->where('registrations.data.0.title', 'Doc 25')
        );

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('registrations.data', 5));
});

test('stats reflect counts independent of the status filter', function () {
    $founder = User::factory()->create();

    Document::factory()->create(['submitted_by' => $founder->id, 'status' => DocumentStatus::InReview]);
    Document::factory()->create(['submitted_by' => $founder->id, 'status' => DocumentStatus::Returned]);
    Document::factory()->create(['submitted_by' => $founder->id, 'status' => DocumentStatus::Approved]);
    Document::factory()->create(['submitted_by' => $founder->id, 'status' => DocumentStatus::Rejected]);

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index', ['status' => 'approved']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registrations.data', 1)
            ->where('stats.total', 4)
            ->where('stats.inProgress', 2)
            ->where('stats.approved', 1)
            ->where('stats.rejected', 1)
        );
});

test('each row\'s href points at registrations.show for that document', function () {
    $founder = User::factory()->create();
    $document = submitFoundingRegistration($founder, 'Founder Org');

    $this->actingAs($founder)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registrations.data.0.href', route('registrations.show', $document))
        );
});
