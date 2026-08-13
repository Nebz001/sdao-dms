<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\SchoolEmailDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @param  'student'|'staff'|null  $audience  Restrict the school-email domain check to one audience.
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, ?string $audience = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId, $audience),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * Every account email must be an NU Lipa school address — the domain
     * check runs before the uniqueness lookup so a personal address is
     * rejected without touching the database.
     *
     * @param  'student'|'staff'|null  $audience  Restrict to one domain list, or null to accept any configured domain.
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null, ?string $audience = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            new SchoolEmailDomain($audience),
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate a student/staff ID number.
     *
     * Required student self-registration IDs follow NU Lipa's confirmed
     * format: a 4-digit year, a dash, then a 6-digit number (e.g.
     * 2023-182854). Optional admin-provisioned staff IDs have no confirmed
     * format yet, so they validate as a reasonable non-empty identifier.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function idNumberRules(bool $required, ?int $userId = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            $required ? 'regex:/^\d{4}-\d{6}$/' : 'regex:/^[A-Za-z0-9-]{5,32}$/',
            $userId === null
                ? Rule::unique(User::class, 'id_number')
                : Rule::unique(User::class, 'id_number')->ignore($userId),
        ];
    }
}
