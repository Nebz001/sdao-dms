<?php

use App\Enums\Role;
use App\Identity\EmailVerification\EmailVerificationCodeService;
use App\Mail\EmailVerificationCodeMail;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

// ── User model: name stays "First Last" ──────────────────────────────────

test('name is built from first and last name when a user is created', function () {
    $user = User::factory()->create(['first_name' => 'Pia Jasmin I.', 'last_name' => 'Quizon']);

    expect($user->fresh()->name)->toBe('Pia Jasmin I. Quizon');
});

test('changing first or last name rebuilds name', function () {
    $user = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);

    $user->update(['last_name' => 'Dela Cruz']);

    expect($user->fresh()->name)->toBe('Juan Dela Cruz');
});

test('writing only name still fills first and last name', function () {
    $user = User::factory()->create();

    $user->update(['name' => 'Ramon Dela Cruz']);

    expect($user->fresh())->first_name->toBe('Ramon')->last_name->toBe('Dela Cruz');
});

test('a factory name override is split consistently', function () {
    $user = User::factory()->create(['name' => 'Carl Justin Magpantay']);

    expect($user)->first_name->toBe('Carl Justin')->last_name->toBe('Magpantay')->name->toBe('Carl Justin Magpantay');
});

test('the shared auth user carries first and last name', function () {
    $user = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);

    $this->actingAs($user)->withoutVite()->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.first_name', 'Juan')
            ->where('auth.user.last_name', 'Cruz')
            ->where('auth.user.name', 'Juan Cruz'));
});

// ── Registration ─────────────────────────────────────────────────────────

test('registering with first and last name creates an account named First Last', function () {
    [$user] = registerViaHttp(['first_name' => '  Juan  ', 'last_name' => ' Dela   Cruz ']);

    expect($user)->first_name->toBe('Juan')->last_name->toBe('Dela Cruz')->name->toBe('Juan Dela Cruz');
});

test('registration accepts enye, periods, hyphens and apostrophes', function () {
    [$user] = registerViaHttp(['first_name' => 'Ma. Cristina', 'last_name' => "Muñoz-D'Souza"]);

    expect($user->name)->toBe("Ma. Cristina Muñoz-D'Souza");
});

test('registration requires both names, with plain-word errors', function () {
    Mail::fake();

    $this->post(route('register.store'), [
        'email' => 'nonames@students.nu-lipa.edu.ph',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors([
        'first_name' => 'Enter a first name.',
        'last_name' => 'Enter a last name.',
    ]);

    Mail::assertNothingQueued();
});

test('registration rejects digits and symbols in a name', function (string $field, string $value) {
    Mail::fake();

    $this->post(route('register.store'), [
        'first_name' => 'Juan', 'last_name' => 'Cruz',
        $field => $value,
        'email' => 'badname@students.nu-lipa.edu.ph',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors($field);

    Mail::assertNothingQueued();
})->with([
    'digits in first name' => ['first_name', 'Juan2'],
    'symbol in last name' => ['last_name', 'Cruz@'],
    'leading hyphen' => ['first_name', '-Juan'],
    'too long' => ['last_name', str_repeat('a', 101)],
]);

test('a code issued before the split still creates an account from its old name', function () {
    Mail::fake();
    $email = 'inflight@students.nu-lipa.edu.ph';

    app(EmailVerificationCodeService::class)->issue(
        email: $email,
        purpose: 'registration',
        payload: ['name' => 'Juan Dela Cruz', 'password' => bcrypt('password'), 'id_number' => '2023-000001'],
    );

    $code = null;
    Mail::assertQueued(EmailVerificationCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->withSession(['pending_registration_email' => $email])
        ->post(route('register.verify.store'), ['code' => $code]);

    expect(User::where('email', $email)->firstOrFail())
        ->first_name->toBe('Juan')->last_name->toBe('Dela Cruz');
});

// ── Profile settings ─────────────────────────────────────────────────────

test('profile update saves first and last name and keeps name in sync', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => 'Maria Luisa', 'last_name' => 'Dela Peña', 'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->fresh())->first_name->toBe('Maria Luisa')->last_name->toBe('Dela Peña')->name->toBe('Maria Luisa Dela Peña');
});

test('profile update requires both names and trims them', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => '   ', 'last_name' => '', 'email' => $user->email,
    ])->assertSessionHasErrors(['first_name' => 'Enter a first name.', 'last_name' => 'Enter a last name.']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => '  Juan ', 'last_name' => ' Cruz  ', 'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Juan Cruz');
});

test('profile update rejects a name with digits', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => 'Juan1', 'last_name' => 'Cruz', 'email' => $user->email,
    ])->assertSessionHasErrors(['first_name' => 'First name can only have letters, spaces, periods, hyphens, and apostrophes, and must start with a letter.']);
});

// ── SDAO provisioning ────────────────────────────────────────────────────

describe('approver provisioning', function () {
    beforeEach(function () {
        $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
        $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    });

    test('the form saves first and last name and the account reads First Last', function () {
        $this->actingAs($this->sdao)->post(route('admin.approvers.store'), [
            'first_name' => 'Avelino D.', 'last_name' => 'Palupit',
            'email' => 'named@nu-lipa.edu.ph', 'role' => Role::Adviser->value,
        ])->assertSessionHasNoErrors();

        expect(User::where('email', 'named@nu-lipa.edu.ph')->firstOrFail())
            ->first_name->toBe('Avelino D.')->last_name->toBe('Palupit')->name->toBe('Avelino D. Palupit');
    });

    test('the form requires both names', function () {
        $this->actingAs($this->sdao)->post(route('admin.approvers.store'), [
            'email' => 'nameless@nu-lipa.edu.ph', 'role' => Role::Adviser->value,
        ])->assertSessionHasErrors(['first_name' => 'Enter a first name.', 'last_name' => 'Enter a last name.']);

        expect(User::where('email', 'nameless@nu-lipa.edu.ph')->exists())->toBeFalse();
    });

    test('the form rejects symbols in a name', function () {
        $this->actingAs($this->sdao)->post(route('admin.approvers.store'), [
            'first_name' => 'Juan<script>', 'last_name' => 'Cruz',
            'email' => 'symbols@nu-lipa.edu.ph', 'role' => Role::Adviser->value,
        ])->assertSessionHasErrors('first_name');
    });
});

// ── Name search matches first, last or full name ─────────────────────────

test('the name scope matches first name, last name and the full name', function () {
    $juan = User::factory()->create(['first_name' => 'Juan Carlo', 'last_name' => 'Dela Cruz']);
    User::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

    expect(User::query()->whereNameMatches('Carlo')->pluck('id')->all())->toBe([$juan->id])
        ->and(User::query()->whereNameMatches('Dela Cruz')->pluck('id')->all())->toBe([$juan->id])
        ->and(User::query()->whereNameMatches('Juan Carlo Dela')->pluck('id')->all())->toBe([$juan->id])
        ->and(User::query()->whereNameMatches('Nobody')->count())->toBe(0);
});

test('the admin account search finds a person by last name', function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $staff = User::factory()->create(['first_name' => 'Bernie', 'last_name' => 'Fabito', 'email' => 'fabito@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $staff->id, 'role' => Role::AcademicDirector]);

    $ids = collect($this->actingAs($sdao)->getJson(route('admin.accounts.search', ['q' => 'Fabito']))->json('accounts'))->pluck('id');

    expect($ids)->toContain($staff->id);
});

test('the adviser typeahead finds an adviser by first or last name', function () {
    $adviser = User::factory()->create(['first_name' => 'Nora', 'last_name' => 'Espiritu']);
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);
    $student = User::factory()->create();

    foreach (['Nora', 'Espiritu', 'Nora Esp'] as $term) {
        $ids = collect($this->actingAs($student)->getJson(route('registrations.adviser-search', ['q' => $term]))->json('advisers'))->pluck('id');

        expect($ids)->toContain($adviser->id);
    }
});
