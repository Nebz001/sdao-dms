<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A test that overrides `name` gets a matching first/last split; the
            // User model rebuilds `name` from first_name/last_name on save.
            'first_name' => fn (array $attributes) => isset($attributes['name'])
                ? PersonName::split($attributes['name'])['first']
                : fake()->firstName(),
            'last_name' => fn (array $attributes) => isset($attributes['name'])
                ? (PersonName::split($attributes['name'])['last'] ?: null)
                : fake()->lastName(),
            // Every account must hold an NU Lipa school address — defaults to
            // the student domain since most factory-created users in tests
            // are unaffiliated actors, not provisioned staff.
            'email' => fake()->unique()->userName().'@students.nu-lipa.edu.ph',
            'email_verified_at' => now(),
            // Test/dev accounts are trusted by default so existing acting-as
            // tests keep working; self-registration (RegistrationController) sets
            // Unverified explicitly instead of relying on this default.
            'account_status' => AccountStatus::Verified,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * `make()` never saves, so the model's saving hook has not built `name`.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->name ??= PersonName::join($user->first_name, $user->last_name);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Simulates a fresh self-registered account awaiting SDAO review.
     */
    public function unverifiedAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => AccountStatus::Unverified,
        ]);
    }

    /**
     * Simulates a self-registered account SDAO has declined.
     */
    public function rejectedAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => AccountStatus::Rejected,
        ]);
    }
}
