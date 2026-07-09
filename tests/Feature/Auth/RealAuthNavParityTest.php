<?php

use App\Enums\AccountStatus;
use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
});

/**
 * The sidebar (resources/js/components/app-sidebar.tsx) is a pure client
 * component that derives its Submit/My Documents sections from the shared
 * `auth.isActiveOfficer` Inertia prop (sourced from OrganizationMembership,
 * not a RoleAssignment proxy). "Nav renders identically for a real-auth user
 * as for a seeded test session" therefore reduces to: does a real
 * Fortify-authenticated, verified, adviser-bound student get
 * `isActiveOfficer: true`, the same as an `actingAs()` seeded student in the
 * rest of the suite? This proves it end-to-end through the REAL login route
 * (`login.store`), not `actingAs()`.
 */
test('a real Fortify-authenticated, adviser-bound student gets the same auth.isActiveOfficer signal a seeded test session would', function () {
    // 1. Self-register a real account (the actual Fortify pipeline).
    $this->post(route('register.store'), [
        'name' => 'Real Auth Student',
        'email' => 'real-auth-student@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $student = User::where('email', 'real-auth-student@example.test')->firstOrFail();

    // 2. Mark email-verified AND SDAO account-Verified (skips the email
    // round-trip and the Pending Accounts queue; both enforcement paths have
    // their own dedicated tests — EmailVerificationEnforcementTest and
    // AccountVerificationGateTest/PendingAccountsTest respectively). Binding
    // now requires account_status = Verified (see BindOrganizationOfficer).
    $student->forceFill([
        'email_verified_at' => now(),
        'account_status' => AccountStatus::Verified,
    ])->save();

    // 3. The adviser binds them as an officer through the real route.
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $student->id,
        'position' => OfficerPosition::President->value,
    ]);

    // 4. Log in through Fortify's REAL login route — not actingAs().
    // Must log out the adviser first: Fortify's login route is guest-gated,
    // so an already-authenticated session would otherwise silently bypass it.
    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login'), [
        'email' => 'real-auth-student@example.test',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($student->fresh());

    // 5. Hit any page and inspect the shared auth.isActiveOfficer prop the sidebar reads.
    $response = $this->withoutVite()->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.isActiveOfficer', true)
    );
});

test('the same auth.isActiveOfficer signal appears for an equivalent seeded student via actingAs', function () {
    $seededStudent = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society

    $this->actingAs($seededStudent);

    $response = $this->withoutVite()->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.isActiveOfficer', true)
    );
});
