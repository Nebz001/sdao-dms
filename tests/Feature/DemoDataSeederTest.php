<?php

use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\RealRosterSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Supabase attachment migration — DemoDataSeeder pins filesystems.attachments
 * to 'local' for the duration of its run() (see the seeder's own docblock
 * comment at the top of run()), so `demo:reset` never uploads its throwaway
 * UploadedFile::fake() attachments into the real Supabase bucket regardless
 * of what ATTACHMENTS_DISK is configured to. This is the only test exercising
 * that override.
 *
 * Seeds in the same order as the confirmed-correct dev-DB restore sequence
 * (migrate:fresh -> db:seed [DatabaseSeeder: Settings -> RealRoster ->
 * WorkflowTemplate] -> db:seed IdentitySeeder -> demo:reset): RealRosterSeeder
 * runs BEFORE IdentitySeeder, not after. DemoDataSeeder deliberately depends
 * on IdentitySeeder's placeholder accounts (adviser-one/-two/-shs) coexisting
 * alongside the real roster — this is intentional "IdentitySeeder pollution,"
 * not a mistake (see DemoDataSeeder's own class docblock on SDAO resolution).
 * But RoleDirectory::resolveGlobal() (used for the Assistant/Academic/
 * Executive Director singleton roles) has no explicit ordering, so it picks
 * whichever role_assignments row got the lower id — seeding RealRosterSeeder
 * first ensures the REAL named directors get the lower ids and are the ones
 * resolveGlobal() actually picks, exactly as happens in production. Getting
 * this order backwards silently resolves IdentitySeeder's placeholder
 * director instead and breaks approveEntireChain() with an
 * UnauthorizedApproverException.
 *
 * IdentitySeeder also founds 3 placeholder orgs (Computing Society, IT Guild,
 * SHS Council) and binds adviser-one/-two/-shs to them. The real `demo:reset`
 * command (see ResetDemoData::wipe()) always deletes every Organization
 * before DemoDataSeeder runs — and role_assignments.organization_id is
 * nullOnDelete — freeing those advisers so DemoDataSeeder can rebind them to
 * CODECS/UAPSA/Venaris Esports. Mirror that wipe here too, or
 * ApproveOrganizationRegistration's adviser-exclusivity re-check rejects the
 * very first founding approval.
 */
test('DemoDataSeeder writes every seeded attachment to the local disk, never supabase', function () {
    $this->seed([SettingsSeeder::class, RealRosterSeeder::class, WorkflowTemplateSeeder::class, IdentitySeeder::class]);
    Organization::query()->delete();

    app(DemoDataSeeder::class)->run();

    $attachments = DocumentAttachment::all();

    expect($attachments)->not->toBeEmpty();

    foreach ($attachments as $attachment) {
        expect($attachment->disk)->toBe('local');
        Storage::disk('local')->assertExists($attachment->path);
    }

    // Nothing seeded should have reached the (faked) supabase disk at all.
    expect(Storage::disk('supabase')->allFiles())->toBeEmpty();
});

/**
 * demo:reset keeps `users` and `role_assignments` but wipes organizations, so
 * a second run meets every demo account the first one created. The seeder
 * must reuse them by email instead of colliding on users_email_unique.
 */
test('DemoDataSeeder can run twice in a row, reusing its demo accounts', function () {
    $this->seed([SettingsSeeder::class, RealRosterSeeder::class, WorkflowTemplateSeeder::class, IdentitySeeder::class]);
    Organization::query()->delete();

    app(DemoDataSeeder::class)->run();

    $userCount = User::count();
    $roleAssignmentCount = RoleAssignment::count();
    $organizationCount = Organization::count();

    // Same wipe demo:reset does between runs: organizations (cascading to
    // documents). Reports go first only because SQLite checks the restrictive
    // report -> proposal key before the cascade reaches them; Postgres does not.
    DB::table('after_activity_reports')->delete();
    Organization::query()->delete();

    app(DemoDataSeeder::class)->run();

    expect(User::count())->toBe($userCount)
        ->and(RoleAssignment::count())->toBe($roleAssignmentCount)
        ->and(Organization::count())->toBe($organizationCount)
        ->and(User::where('email', 'roxasm@nu-lipa.edu.ph')->count())->toBe(1);
});
