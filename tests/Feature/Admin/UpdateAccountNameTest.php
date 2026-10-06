<?php

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->target = User::factory()->create(['first_name' => 'Juan Dela', 'last_name' => 'Cruz', 'email' => 'target@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $this->target->id, 'role' => Role::Adviser->value]);
});

function nameUrl(User $user): string
{
    return route('admin.accounts.name.update', $user);
}

test('an SDAO member can correct first and last name and all three columns agree', function () {
    $this->actingAs($this->sdao)
        ->patch(nameUrl($this->target), ['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.title', 'Name updated');

    expect($this->target->fresh())->first_name->toBe('Juan')->last_name->toBe('Dela Cruz')->name->toBe('Juan Dela Cruz');
});

test('a title the account already shows is kept when its name is corrected', function () {
    $target = User::factory()->create(['name' => 'Dr. Fatima Santiago']);

    $this->actingAs($this->sdao)->patch(nameUrl($target), ['first_name' => 'Fatima', 'last_name' => 'Santiago-Reyes']);

    expect($target->fresh())->name->toBe('Dr. Fatima Santiago-Reyes')->first_name->toBe('Fatima');
});

test('a title typed into the first-name field is kept out of first_name', function () {
    $this->actingAs($this->sdao)->patch(nameUrl($this->target), ['first_name' => 'Dr. Juan', 'last_name' => 'Cruz']);

    expect($this->target->fresh())->first_name->toBe('Juan')->last_name->toBe('Cruz');
});

test('the edit uses the same validation as every other name form', function () {
    $this->actingAs($this->sdao)
        ->patch(nameUrl($this->target), ['first_name' => '', 'last_name' => 'Cruz9'])
        ->assertSessionHasErrors([
            'first_name' => 'Enter a first name.',
            'last_name' => 'Last name can only have letters, spaces, periods, hyphens, and apostrophes, and must start with a letter.',
        ]);

    $this->actingAs($this->sdao)
        ->patch(nameUrl($this->target), ['first_name' => str_repeat('a', 101), 'last_name' => 'Cruz'])
        ->assertSessionHasErrors('first_name');

    expect($this->target->fresh()->name)->toBe('Juan Dela Cruz');
});

test('it also works for a student account', function () {
    $student = User::factory()->create();

    $this->actingAs($this->sdao)->patch(nameUrl($student), ['first_name' => 'Ma. Ñina', 'last_name' => "O'Brien"])->assertSessionHasNoErrors();

    expect($student->fresh()->name)->toBe("Ma. Ñina O'Brien");
});

test('only SDAO can edit a name', function (string $who) {
    $actor = match ($who) {
        'student' => User::factory()->create(),
        'adviser' => $this->target,
        'director' => User::where('email', 'asst-director@nu-lipa.edu.ph')->first() ?? User::factory()->create(),
    };

    $this->actingAs($actor)->patch(nameUrl(User::factory()->create()), ['first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();

    expect($this->target->fresh()->name)->toBe('Juan Dela Cruz');
})->with(['student', 'adviser', 'director']);

test('a guest is sent to log in', function () {
    $this->patch(nameUrl($this->target), ['first_name' => 'X', 'last_name' => 'Y'])->assertRedirect(route('login'));
});

test('the approver accounts page sends first and last name for the edit form', function () {
    $this->actingAs($this->sdao)->withoutVite()->get(route('admin.approvers.index'))
        ->assertInertia(fn ($page) => $page->where('approvers', fn ($accounts) => collect($accounts)->contains(
            fn ($a) => $a['id'] === $this->target->id && $a['first_name'] === 'Juan Dela' && $a['last_name'] === 'Cruz'
        )));
});
